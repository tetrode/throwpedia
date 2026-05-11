<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tetrode\Throwpedia\DTO\AnalyzerConfig;
use Tetrode\Throwpedia\DTO\AttributeField;
use Tetrode\Throwpedia\DTO\ExceptionCatalog;
use Tetrode\Throwpedia\DTO\OutputTarget;
use Tetrode\Throwpedia\DTO\ThrowpediaConfig;
use Tetrode\Throwpedia\DTO\ValidationIssue;
use Tetrode\Throwpedia\IO\ConsoleOutput;
use Tetrode\Throwpedia\IO\OutputInterface;

class Throwpedia
{
    public function __construct(
        private readonly OutputInterface $output = new ConsoleOutput(),
    ) {
    }

    /**
     * @param string[] $argv
     */
    public function run(array $argv): void
    {
        $configLoader = new ConfigLoader($this->output);
        $configFile = $configLoader->getConfigFile($argv);
        $config = $configLoader->load($configFile, 'throwpedia.neon');

        $files = $this->collectFiles($config->sources, $config->projectRoot);
        $analyzer = $this->initAnalyzer($config);

        $catalog = $analyzer->analyze($files);
        $this->processResults($catalog, $config->outputs, $config->projectRoot, $config->fields);

        $this->output->writeln('Analysis complete.');

        $this->displayValidationIssues($analyzer);
    }

    /**
     * @param array<string>|string $sources
     *
     * @return array<string>
     */
    private function collectFiles(array|string $sources, string $projectRoot): array
    {
        $sources = (array)$sources;
        $files = [];

        foreach ($sources as $srcDirRel) {
            $srcDir = str_starts_with($srcDirRel, '/') ? $srcDirRel : $projectRoot . DIRECTORY_SEPARATOR . $srcDirRel;

            if (!is_dir($srcDir)) {
                if (file_exists($srcDir) && 'php' === pathinfo($srcDir, PATHINFO_EXTENSION)) {
                    $files[] = $srcDir;
                } else {
                    $this->output->warning("Source directory or file '$srcDir' not found.");
                }
                continue;
            }

            $directory = new RecursiveDirectoryIterator($srcDir);
            $iterator = new RecursiveIteratorIterator($directory);
            foreach ($iterator as $file) {
                /** @var SplFileInfo $file */
                if ($file->isFile() && 'php' === $file->getExtension()) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    private function initAnalyzer(ThrowpediaConfig $config): Analyzer
    {
        return new Analyzer(
            $this->output,
            new AnalyzerConfig(
                attributes: $config->attributes,
                fields: $config->fields,
                allowDirectNew: $config->allowDirectNew,
                projectRoot: $config->projectRoot,
            )
        );
    }

    /**
     * @param OutputTarget[] $outputs
     * @param DTO\AttributeField[] $fields
     */
    private function processResults(ExceptionCatalog $catalog, array $outputs, string $projectRoot, array $fields): void
    {
        if (empty($outputs)) {
            $this->output->write(Renderers::toText($catalog, $fields));
            return;
        }

        foreach ($outputs as $target) {
            $fullPath = str_starts_with($target->path, '/') ? $target->path : $projectRoot . DIRECTORY_SEPARATOR . $target->path;

            $dir = \dirname($fullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0o777, true);
            }

            $content = $this->renderModel($catalog, $target->extension, $fields);

            if (null !== $content) {
                file_put_contents($fullPath, $content);
                $this->output->writeln("Generated: $fullPath");
            } else {
                $this->output->warning("Unknown output extension '{$target->extension}' for $fullPath");
            }
        }
    }

    /**
     * @param AttributeField[] $fields
     *
     * @throws \JsonException
     */
    private function renderModel(ExceptionCatalog $catalog, string $extension, array $fields): ?string
    {
        return match (strtolower($extension)) {
            'json'           => Renderers::toJson($catalog),
            'yaml', 'yml'    => Renderers::toYaml($catalog),
            'md', 'markdown' => Renderers::toMarkdown($catalog, $fields),
            default          => null,
        };
    }

    private function displayValidationIssues(Analyzer $analyzer): void
    {
        $issues = $analyzer->getValidationIssues();

        if (empty($issues)) {
            $this->output->writeln("\nNo validation issues found.");
            return;
        }

        $errors = array_filter($issues, fn (ValidationIssue $i) => ValidationIssue::SEVERITY_ERROR === $i->severity);
        $warnings = array_filter($issues, fn (ValidationIssue $i) => ValidationIssue::SEVERITY_WARNING === $i->severity);

        if (!empty($errors)) {
            $this->output->writeln("\nValidation Errors found:");
            foreach ($errors as $issue) {
                $this->output->error($this->formatIssue($issue));
            }
        }

        if (!empty($warnings)) {
            $this->output->writeln("\nValidation Warnings found:");
            foreach ($warnings as $issue) {
                $this->output->warning($this->formatIssue($issue));
            }
        }
    }

    private function formatIssue(ValidationIssue $issue): string
    {
        if ($issue->file) {
            return \sprintf(
                '%s at %s:%d (%s::%s)',
                $issue->message,
                $issue->file,
                $issue->line ?? 0,
                $issue->class ?? '',
                $issue->method ?? ''
            );
        }

        return $issue->message;
    }
}
