<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia;

use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Tetrode\Throwpedia\DTO\AnalyzerConfig;
use Tetrode\Throwpedia\DTO\DirectNewThrow;
use Tetrode\Throwpedia\DTO\ExceptionModelEntry;
use Tetrode\Throwpedia\DTO\MethodAnalysisResult;
use Tetrode\Throwpedia\IO\OutputInterface;

class Analyzer
{
    private Parser $parser;
    private Extractor $extractor;
    private bool $allowDirectNew = false;
    /** @var string[] */
    private array $targetAttributes = ['ExceptionReason'];
    /** @var string[] */
    private array $validationWarnings = [];
    private string $projectRoot = '';

    public function __construct(
        private readonly OutputInterface $output,
        private readonly AnalyzerConfig $config = new AnalyzerConfig()
    ) {
        $this->parser = new ParserFactory()->createForNewestSupportedVersion();
        $this->extractor = new Extractor($this->output);

        $this->targetAttributes = $this->config->attributes;
        $this->extractor->setTargetAttributes($this->targetAttributes);

        $this->allowDirectNew = $this->config->allowDirectNew;
        $this->projectRoot = $this->config->projectRoot;
    }

    /**
     * @param string[] $files
     *
     * @return array<string, ExceptionModelEntry>
     */
    public function analyze(array $files): array
    {
        $nameResolver = new NameResolver();
        $resolverTraverser = new NodeTraverser();
        $resolverTraverser->addVisitor($nameResolver);

        $extractorTraverser = new NodeTraverser();
        $extractorTraverser->addVisitor($this->extractor);

        foreach ($files as $file) {
            try {
                $code = @file_get_contents($file);
                if (false === $code) {
                    continue;
                }

                $stmts = $this->parser->parse($code);
                if (null === $stmts) {
                    continue;
                }

                $this->extractor->setCurrentFile($file);

                $stmts = $resolverTraverser->traverse($stmts);
                $extractorTraverser->traverse($stmts);
            } catch (Error $e) {
                $this->output->error("Parse Error in {$file}: {$e->getMessage()}");
            }
        }

        return $this->buildModel(
            $this->extractor->getResults(),
            $this->extractor->getDirectNewThrows()
        );
    }

    /**
     * @param array<string, MethodAnalysisResult> $rawResults
     * @param DirectNewThrow[] $directNew
     *
     * @return array<string, ExceptionModelEntry>
     */
    private function buildModel(array $rawResults, array $directNew): array
    {
        /** @var array<string, ExceptionModelEntry> $model */
        $model = [];
        foreach ($rawResults as $methodData) {
            $location = \sprintf('%s::%s', $methodData->class, $methodData->method);
            $methodExceptions = array_unique($methodData->throws);
            $exceptionsStr = implode(', ', $methodExceptions) ?: 'unknown';

            foreach ($methodData->attributes as $attr) {
                $code = $attr->code;
                $tech = $attr->technical;
                $biz = $attr->business;

                $foundKey = null;
                foreach ($model as $existingKey => $entry) {
                    $entryCode = $entry->code ?? $existingKey;
                    if ($entryCode === $code && $entry->technical === $tech && $entry->business === $biz) {
                        $foundKey = $existingKey;
                        break;
                    }
                }

                if (null !== $foundKey) {
                    $entry = $model[$foundKey];
                    $newThrownFrom = $entry->thrown_from;
                    if (!\in_array($location, $newThrownFrom, true)) {
                        $newThrownFrom[] = $location;
                    }
                    // Add any new exceptions from this method to the existing entry
                    $existingExceptions = explode(', ', $entry->exception);
                    foreach ($methodExceptions as $ex) {
                        if (!\in_array($ex, $existingExceptions, true)) {
                            $existingExceptions[] = $ex;
                        }
                    }
                    $model[$foundKey] = new ExceptionModelEntry(
                        business: $entry->business,
                        technical: $entry->technical,
                        exception: implode(', ', array_filter($existingExceptions)),
                        thrown_from: $newThrownFrom,
                        code: $entry->code
                    );
                } else {
                    $uniqueKey = $code;
                    $counter = 1;
                    while (isset($model[$uniqueKey])) {
                        if (1 === $counter) {
                            $this->validationWarnings[] = \sprintf(
                                "Duplicate code '%s' found with different reasons at %s:%d (%s).",
                                $code,
                                $this->getDisplayPath($methodData->file),
                                $methodData->line,
                                $location
                            );
                        }
                        $uniqueKey = $code . '_' . $counter++;
                    }

                    $model[$uniqueKey] = new ExceptionModelEntry(
                        code: $code,
                        business: $biz,
                        technical: $tech,
                        exception: $exceptionsStr,
                        thrown_from: [$location],
                    );
                }
            }
        }

        if ($this->allowDirectNew) {
            $this->appendDirectNewThrows($model, $directNew);
        }

        ksort($model);
        return $model;
    }

    /**
     * @param array<string, ExceptionModelEntry> $model
     * @param DirectNewThrow[] $directNew
     */
    private function appendDirectNewThrows(array &$model, array $directNew): void
    {
        foreach ($directNew as $throw) {
            $exceptionBaseName = strtoupper(basename(str_replace('\\', '/', $throw->exception)));
            $code = 'DIRECT_NEW_' . $exceptionBaseName;

            // Avoid duplicates if multiple direct news of same exception
            $counter = 1;
            $originalCode = $code;
            while (isset($model[$code])) {
                $code = $originalCode . '_' . $counter++;
            }

            $location = \sprintf('%s::%s', $throw->class, $throw->method);
            $model[$code] = new ExceptionModelEntry(
                business: 'Direct instantiation of ' . $throw->exception,
                technical: 'Thrown from ' . $location,
                exception: $throw->exception,
                thrown_from: [$location],
            );
        }
    }

    /**
     * @return string[]
     */
    public function getValidationErrors(): array
    {
        return $this->getValidationErrorsInternal();
    }

    /**
     * @return string[]
     */
    public function getValidationWarnings(): array
    {
        return $this->validationWarnings;
    }

    private function getDisplayPath(string $file): string
    {
        if ($this->projectRoot && str_starts_with($file, $this->projectRoot)) {
            return ltrim(substr($file, \strlen($this->projectRoot)), DIRECTORY_SEPARATOR);
        }

        return $file;
    }

    /**
     * @return string[]
     */
    private function getValidationErrorsInternal(): array
    {
        $errors = [];
        foreach ($this->extractor->getDirectNewThrows() as $throw) {
            $errors[] = \sprintf(
                "Direct 'new' usage at %s:%d in %s::%s for %s",
                $this->getDisplayPath($throw->file),
                $throw->line,
                $throw->class,
                $throw->method,
                $throw->exception
            );
        }

        foreach ($this->extractor->getResults() as $methodData) {
            if (!empty($methodData->throws) && empty($methodData->attributes)) {
                $errors[] = \sprintf(
                    'Missing #[%s] at %s:%d for method %s::%s',
                    implode('|', $this->targetAttributes),
                    $this->getDisplayPath($methodData->file),
                    $methodData->line,
                    $methodData->class,
                    $methodData->method
                );
            }
        }

        return $errors;
    }
}
