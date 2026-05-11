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
        if (empty($outputs)) {
            $this->output->write(Renderers::toText($model));
            return;
        }

        $outputs = (array)$outputs;

        foreach ($outputs as $outputPath) {
            $fullPath = str_starts_with($outputPath, '/') ? $outputPath : $projectRoot . DIRECTORY_SEPARATOR . $outputPath;

            $dir = \dirname($fullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0o777, true);
            }

            $extension = pathinfo($fullPath, PATHINFO_EXTENSION);
            $content = $this->renderModel($model, $extension);

            if (null !== $content) {
                file_put_contents($fullPath, $content);
                if ($verbosity >= 1) {
                    $this->output->writeln("Generated: $fullPath");
                }
            } else {
                $this->output->warning("Unknown output extension '$extension' for $fullPath");
            }
        }
    }

    /**
     * @param array<string, mixed> $model
     */
    private function renderModel(array $model, string $extension): ?string
    {
        return match (strtolower($extension)) {
            'json'           => Renderers::toJson($model),
            'yaml', 'yml'    => Renderers::toYaml($model),
            'md', 'markdown' => Renderers::toMarkdown($model),
            default          => null,
        };
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
