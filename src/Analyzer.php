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
use Tetrode\Throwpedia\DTO\MethodAnalysisResult;
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

        $this->targetAttributes = $this->config->attributes;
        $this->extractor->setTargetAttributes($this->targetAttributes);
        $this->extractor->setFields($this->config->fields);

        $this->allowDirectNew = $this->config->allowDirectNew;
    }

    /**
     * @param string[] $files
     *
     * @return ExceptionCatalog
     */
    public function analyze(array $files): ExceptionCatalog
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

        return $this->buildModel($this->extractor->getExtractionResults());
    }

    private function buildModel(ExtractionResults $results): ExceptionCatalog
    {
        $codeField = 'code';
        foreach ($this->config->fields as $field) {
            if ($field->isCode) {
                $codeField = $field->name;
                break;
            }
        }

        /** @var array<string, ExceptionModelEntry> $model */
        $model = [];
        foreach ($results->methods as $methodData) {
            $location = \sprintf('%s::%s', $methodData->class, $methodData->method);
            $methodExceptions = array_unique($methodData->throws);
            $exceptionsStr = implode(', ', $methodExceptions) ?: 'unknown';

            foreach ($methodData->attributes as $attr) {
                $code = $attr->values[$codeField] ?? 'UNKNOWN';

                $foundKey = null;
                foreach ($model as $existingKey => $entry) {
                    if ($entry->values === $attr->values) {
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
                        values: $entry->values,
                        exception: implode(', ', array_filter($existingExceptions)),
                        thrown_from: $newThrownFrom,
                    );
                } else {
                    $uniqueKey = $code;
                    $counter = 1;
                    while (isset($model[$uniqueKey])) {
                        if (1 === $counter) {
                            $this->validationIssues[] = new ValidationIssue(
                                message: \sprintf("Duplicate code '%s' found with different reasons.", $code),
                                severity: ValidationIssue::SEVERITY_WARNING,
                                file: $methodData->file,
                                line: $methodData->line,
                                class: $methodData->class,
                                method: $methodData->method
                            );
                        }
                        $uniqueKey = $code . '_' . $counter++;
                    }

                    $model[$uniqueKey] = new ExceptionModelEntry(
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
        return new ExceptionCatalog($model);
    }

    /**
     * @param array<string, ExceptionModelEntry> $model
     * @param DirectNewThrow[] $directNew
     */
    private function appendDirectNewThrows(array &$model, array $directNew): void
    {
        foreach ($directNew as $throw) {
            $exceptionBaseName = str_replace('\\', '/', $throw->exception)
                    |> basename(...)
                    |> strtoupper(...);
            $code = 'DIRECT_NEW_' . $exceptionBaseName;

            // Avoid duplicates if multiple direct news of same exception
            $counter = 1;
            $originalCode = $code;
            while (isset($model[$code])) {
                $code = $originalCode . '_' . $counter++;
            }

            $location = \sprintf('%s::%s', $throw->class, $throw->method);

            $values = [];
            foreach ($this->config->fields as $field) {
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

            $model[$code] = new ExceptionModelEntry(
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
