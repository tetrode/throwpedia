<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
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
        $verbosity = $configLoader->getVerbosity($argv);
        $configFile = $configLoader->getConfigFile($argv);
        $config = $configLoader->load($configFile, 'throwpedia.neon');
        $projectRoot = $configLoader->findProjectRoot();

        $files = $this->collectFiles($config['source'] ?? ['src'], $projectRoot);
        $analyzer = $this->initAnalyzer($config, $verbosity);

        $model = $analyzer->analyze($files);
        $this->processResults($model, $config['outputs'] ?? null, $verbosity, $projectRoot);

        $this->output->writeln("Analysis complete.");

        $this->displayValidationErrors($analyzer->getValidationErrors());
    }

    /**
     * @param array<string>|string $sources
     *
     * @return array<string>
     */
    private function collectFiles(array|string $sources, string $projectRoot): array
    {
        if (\is_string($sources)) {
            $sources = [$sources];
        }

        $files = [];
        foreach ($sources as $srcDirRel) {
            $srcDir = str_starts_with($srcDirRel, '/') ? $srcDirRel : $projectRoot . DIRECTORY_SEPARATOR . $srcDirRel;

            if (!is_dir($srcDir)) {
                $this->output->warning("Source directory '$srcDir' not found.");
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

    /**
     * @param array<string, mixed> $config
     */
    private function initAnalyzer(array $config, int $verbosity): Analyzer
    {
        $analyzer = new Analyzer(
            $this->output,
            [
                'attributes'     => $config['attributes'] ?? ['ExceptionReason'],
                'allowDirectNew' => $config['allowDirectNew'] ?? false,
            ]
        );
        $analyzer->setVerbosity($verbosity);

        return $analyzer;
    }

    /**
     * @param array<string, mixed> $model
     */
    private function processResults(array $model, mixed $outputs, int $verbosity, string $projectRoot): void
    {
        if (null === $outputs || (\is_array($outputs) && empty($outputs))) {
            $this->output->write(Renderers::toText($model));
            return;
        }

        if (\is_string($outputs)) {
            $outputs = [$outputs];
        }

        foreach ($outputs as $outputPath) {
            if (!str_starts_with($outputPath, '/')) {
                $outputPath = $projectRoot . DIRECTORY_SEPARATOR . $outputPath;
            }

            $dir = \dirname($outputPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0o777, true);
            }

            $extension = pathinfo($outputPath, PATHINFO_EXTENSION);
            $content = match ($extension) {
                'json'           => Renderers::toJson($model),
                'yaml', 'yml'    => Renderers::toYaml($model),
                'md', 'markdown' => Renderers::toMarkdown($model),
                default          => null,
            };

            if (null !== $content) {
                file_put_contents($outputPath, $content);
                if ($verbosity >= 1) {
                    $this->output->writeln("Generated: $outputPath");
                }
            } else {
                $this->output->warning("Unknown output extension '$extension' for $outputPath");
            }
        }
    }

    /**
     * @param array<string> $errors
     */
    private function displayValidationErrors(array $errors): void
    {
        if (!empty($errors)) {
            $this->output->writeln("\nValidation Errors found:");
            foreach ($errors as $error) {
                $this->output->writeln("- $error");
            }
        } else {
            $this->output->writeln("\nNo validation errors found.");
        }
    }
}
