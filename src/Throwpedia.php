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
use Tetrode\Throwpedia\DTO\ScanMeta;
use Tetrode\Throwpedia\DTO\ThrowpediaConfig;
use Tetrode\Throwpedia\DTO\ValidationIssue;
use Tetrode\Throwpedia\IO\ConsoleOutput;
use Tetrode\Throwpedia\IO\OutputInterface;

class Throwpedia
{
    public const string VERSION = '0.2.0';

    public function __construct(
        private readonly OutputInterface $output = new ConsoleOutput(),
    ) {
    }

    /**
     * @param string[] $argv
     */
    public function run(array $argv): void
    {
        $this->output->writeln('Throwpedia ' . self::VERSION . "\n");

        $configLoader = new ConfigLoader($this->output);
        $configFile = $configLoader->getConfigFile($argv);
        $config = $configLoader->load($configFile, 'throwpedia.neon');

        $files = $this->collectFiles($config->sources, $config->projectRoot);
        $analyzer = $this->initAnalyzer($config);

        $meta = new ScanMeta(
            version: self::VERSION,
            scan_time: date('Y-m-d H:i:s'),
        );

        $catalog = $analyzer->analyze($files, $meta);
        $this->processResults($catalog, $config->outputs, $config->projectRoot, $config->attributeFields);

        $this->output->writeln('Analysis complete.');

        $this->displayValidationIssues($analyzer, $config->projectRoot);
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
                attributeFields: $config->attributeFields,
                allowDirectNew: $config->allowDirectNew,
                suppressDuplicateIdentifierWarning: $config->suppressDuplicateIdentifierWarning,
                projectRoot: $config->projectRoot,
            )
        );
    }

    /**
     * @param OutputTarget[] $outputs
     * @param array<string, AttributeField[]> $attributeFields
     *
     * @throws \JsonException
     */
    private function processResults(ExceptionCatalog $catalog, array $outputs, string $projectRoot, array $attributeFields): void
    {
        if (empty($outputs)) {
            $this->output->write(Renderers::toText($catalog, $attributeFields));
            return;
        }

        foreach ($outputs as $target) {
            $fullPath = str_starts_with($target->path, '/') ? $target->path : $projectRoot . DIRECTORY_SEPARATOR . $target->path;

            $dir = \dirname($fullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0o777, true);
            }

            $content = $this->renderModel($catalog, $target->extension, $attributeFields);

            if (null !== $content) {
                file_put_contents($fullPath, $content);
                $this->output->writeln("Generated: $fullPath");
            } else {
                $this->output->warning("Unknown output extension '{$target->extension}' for $fullPath");
            }
        }
    }

    /**
     * @param array<string, AttributeField[]> $attributeFields
     *
     * @throws \JsonException
     */
    private function renderModel(ExceptionCatalog $catalog, string $extension, array $attributeFields): ?string
    {
        return match (strtolower($extension)) {
            'json'           => Renderers::toJson($catalog),
            'yaml', 'yml'    => Renderers::toYaml($catalog),
            'md', 'markdown' => Renderers::toMarkdown($catalog, $attributeFields),
            'csv'            => Renderers::toCsv($catalog, $attributeFields),
            'tsv'            => Renderers::toTsv($catalog, $attributeFields),
            'psv'            => Renderers::toPsv($catalog, $attributeFields),
            'xml'            => Renderers::toXml($catalog),
            'toml'           => Renderers::toToml($catalog),
            default          => null,
        };
    }

    private function displayValidationIssues(Analyzer $analyzer, string $projectRoot): void
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
                $this->output->error($this->formatIssue($issue, $projectRoot));
            }
        }

        if (!empty($warnings)) {
            $this->output->writeln("\nValidation Warnings found:");
            foreach ($warnings as $issue) {
                $this->output->warning($this->formatIssue($issue, $projectRoot));
            }
        }
    }

    private function formatIssue(ValidationIssue $issue, string $projectRoot): string
    {
        if ($issue->file) {
            $file = $issue->file;
            if (str_starts_with($file, $projectRoot)) {
                $file = ltrim(substr($file, \strlen($projectRoot)), DIRECTORY_SEPARATOR);
            }

            return \sprintf(
                '%s at %s:%d (%s::%s)',
                $issue->message,
                $file,
                $issue->line ?? 0,
                $issue->class ?? '',
                $issue->method ?? ''
            );
        }

        return $issue->message;
    }
}
