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
use Tetrode\Throwpedia\DTO\ExceptionCatalog;
use Tetrode\Throwpedia\DTO\ExceptionModelEntry;
use Tetrode\Throwpedia\DTO\ExtractionResults;
use Tetrode\Throwpedia\DTO\ProjectInfo;
use Tetrode\Throwpedia\DTO\ScanMeta;
use Tetrode\Throwpedia\DTO\ValidationIssue;
use Tetrode\Throwpedia\IO\OutputInterface;

class Analyzer
{
    private Parser $parser;
    private Extractor $extractor;
    private bool $allowDirectNew = false;
    /** @var string[] */
    private array $targetAttributes = ['ExceptionReason'];
    /** @var ValidationIssue[] */
    private array $validationIssues = [];

    public function __construct(
        private readonly OutputInterface $output,
        private readonly AnalyzerConfig $config = new AnalyzerConfig()
    ) {
        $this->parser = new ParserFactory()->createForNewestSupportedVersion();
        $this->extractor = new Extractor($this->output);

        $this->extractor->setAttributeFields($this->config->attributeFields);
        $this->targetAttributes = array_keys($this->config->attributeFields) ?: ['ExceptionReason'];

        $this->allowDirectNew = $this->config->allowDirectNew;
    }

    /**
     * @param string[] $files
     */
    public function analyze(array $files, ScanMeta $meta = new ScanMeta()): ExceptionCatalog
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

        return $this->buildModel($this->extractor->getExtractionResults(), $meta);
    }

    public function buildModel(ExtractionResults $results, ScanMeta $meta = new ScanMeta()): ExceptionCatalog
    {
        /** @var array<string, ExceptionModelEntry> $model */
        $model = [];
        foreach ($results->methods as $methodData) {
            $location = \sprintf('%s::%s', $methodData->class, $methodData->method);
            $methodExceptions = array_unique($methodData->throws);
            $exceptionsStr = implode(', ', $methodExceptions) ?: 'unknown';

            foreach ($methodData->attributes as $attr) {
                $fields = $this->config->attributeFields[$attr->attributeName] ?? [];
                $codeField = 'code';
                foreach ($fields as $field) {
                    if ($field->isCode) {
                        $codeField = $field->name;
                        break;
                    }
                }

                $code = $attr->values[$codeField] ?? 'UNKNOWN';

                $foundKey = null;
                foreach ($model as $existingKey => $entry) {
                    if ($entry->attributeName === $attr->attributeName && $entry->values === $attr->values) {
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
                        attributeName: $attr->attributeName,
                        values: $entry->values,
                        exception: implode(', ', array_filter($existingExceptions)),
                        thrown_from: $newThrownFrom,
                    );
                } else {
                    $uniqueKey = $attr->attributeName . ':' . $code;
                    $counter = 1;
                    while (isset($model[$uniqueKey])) {
                        if (1 === $counter && !$this->config->suppressDuplicateCodeWarning) {
                            $this->validationIssues[] = new ValidationIssue(
                                message: \sprintf("Duplicate code '%s' found with different reasons for attribute '%s'.", $code, $attr->attributeName),
                                severity: ValidationIssue::SEVERITY_WARNING,
                                file: $methodData->file,
                                line: $methodData->line,
                                class: $methodData->class,
                                method: $methodData->method
                            );
                        }
                        $uniqueKey = $attr->attributeName . ':' . $code . '_' . $counter++;
                    }

                    $model[$uniqueKey] = new ExceptionModelEntry(
                        attributeName: $attr->attributeName,
                        values: $attr->values,
                        exception: $exceptionsStr,
                        thrown_from: [$location],
                    );
                }
            }
        }

        if ($this->allowDirectNew) {
            $this->appendDirectNewThrows($model, $results->directNewThrows);
        }

        ksort($model);

        $projectInfo = $this->getProjectInfo($this->config->projectRoot, \count($model));

        return new ExceptionCatalog($model, $projectInfo, $meta);
    }

    private function getProjectInfo(string $projectRoot, int $totalExceptions): ProjectInfo
    {
        $name = 'unknown';
        $php = 'unknown';

        $composerJsonPath = $projectRoot . DIRECTORY_SEPARATOR . 'composer.json';
        if (file_exists($composerJsonPath)) {
            $composerJson = json_decode((string)file_get_contents($composerJsonPath), true);
            if (\is_array($composerJson)) {
                $name = $composerJson['name'] ?? 'unknown';
                $php = $composerJson['require']['php'] ?? 'unknown';
            }
        }

        return new ProjectInfo($name, $php, $totalExceptions);
    }

    /**
     * @param array<string, ExceptionModelEntry> $model
     * @param DirectNewThrow[] $directNew
     */
    private function appendDirectNewThrows(array &$model, array $directNew): void
    {
        $firstAttrName = array_key_first($this->config->attributeFields) ?: 'ExceptionReason';
        $fields = $this->config->attributeFields[$firstAttrName] ?? [];

        foreach ($directNew as $throw) {
            $exceptionBaseName = str_replace('\\', '/', $throw->exception)
                    |> basename(...)
                    |> strtoupper(...);
            $code = 'DIRECT_NEW_' . $exceptionBaseName;

            // Avoid duplicates if multiple direct news of same exception
            $counter = 1;
            $originalCode = $code;
            $uniqueKey = $firstAttrName . ':' . $code;
            while (isset($model[$uniqueKey])) {
                $uniqueKey = $firstAttrName . ':' . $originalCode . '_' . $counter++;
            }

            $location = \sprintf('%s::%s', $throw->class, $throw->method);

            $values = [];
            foreach ($fields as $field) {
                if ($field->isCode) {
                    $values[$field->name] = $code;
                } elseif (str_contains(strtolower($field->label), 'business')) {
                    $values[$field->name] = 'Direct instantiation of ' . $throw->exception;
                } elseif (str_contains(strtolower($field->label), 'technical')) {
                    $values[$field->name] = 'Thrown from ' . $location;
                } else {
                    $values[$field->name] = '';
                }
            }

            $model[$uniqueKey] = new ExceptionModelEntry(
                attributeName: $firstAttrName,
                values: $values,
                exception: $throw->exception,
                thrown_from: [$location],
            );
        }
    }

    /**
     * @return ValidationIssue[]
     */
    public function getValidationIssues(): array
    {
        $results = $this->extractor->getExtractionResults();
        $issues = array_merge($this->validationIssues, $results->validationIssues);

        if (!$this->allowDirectNew) {
            foreach ($results->directNewThrows as $throw) {
                $issues[] = new ValidationIssue(
                    message: \sprintf("Direct 'new' usage for %s", $throw->exception),
                    severity: ValidationIssue::SEVERITY_ERROR,
                    file: $throw->file,
                    line: $throw->line,
                    class: $throw->class,
                    method: $throw->method
                );
            }
        }

        foreach ($results->methods as $methodData) {
            if (!empty($methodData->throws) && empty($methodData->attributes)) {
                $issues[] = new ValidationIssue(
                    message: \sprintf('Missing #[%s]', implode('|', $this->targetAttributes)),
                    severity: ValidationIssue::SEVERITY_ERROR,
                    file: $methodData->file,
                    line: $methodData->line,
                    class: $methodData->class,
                    method: $methodData->method
                );
            }
        }

        return $issues;
    }

}
