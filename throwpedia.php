<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class Throwpedia
{
    /**
     * @param string[] $argv
     */
    public static function run(array $argv): void
    {
        $verbosity = ConfigLoader::getVerbosity($argv);
        $configFile = ConfigLoader::getConfigFile($argv);
        $config = ConfigLoader::load($configFile, __DIR__ . '/throwpedia.neon');
        $files = self::collectFiles($config['source'] ?? ['src']);
        $analyzer = self::initAnalyzer($config, $verbosity);

        $model = $analyzer->analyze($files);
        self::processResults($model, $config['outputs'] ?? null, $verbosity);

        echo "Analysis complete.\n";

        self::displayValidationErrors($analyzer->getValidationErrors());
    }

    /**
     * @param array<string>|string $sources
     *
     * @return array<string>
     */
    private static function collectFiles(array|string $sources): array
    {
        if (\is_string($sources)) {
            $sources = [$sources];
        }

        $files = [];
        foreach ($sources as $srcDirRel) {
            $srcDir = str_starts_with($srcDirRel, '/') ? $srcDirRel : __DIR__ . '/' . $srcDirRel;

            if (!is_dir($srcDir)) {
                echo "Warning: Source directory '$srcDir' not found.\n";
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
    private static function initAnalyzer(array $config, int $verbosity): Analyzer
    {
        $analyzer = new Analyzer([
            'attributes'     => $config['attributes'] ?? ['ExceptionReason'],
            'allowDirectNew' => $config['allowDirectNew'] ?? false,
        ]);
        $analyzer->setVerbosity($verbosity);

        return $analyzer;
    }

    /**
     * @param array<string, mixed> $model
     */
    private static function processResults(array $model, mixed $outputs, int $verbosity): void
    {
        if (null === $outputs || (\is_array($outputs) && empty($outputs))) {
            echo Renderers::toText($model);
            return;
        }

        if (\is_string($outputs)) {
            $outputs = [$outputs];
        }

        foreach ($outputs as $outputPath) {
            if (!str_starts_with($outputPath, '/')) {
                $outputPath = __DIR__ . '/' . $outputPath;
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
                    echo "Generated: $outputPath\n";
                }
            } else {
                echo "Warning: Unknown output extension '$extension' for $outputPath\n";
            }
        }
    }

    /**
     * @param array<string> $errors
     */
    private static function displayValidationErrors(array $errors): void
    {
        if (!empty($errors)) {
            echo "\nValidation Errors found:\n";
            foreach ($errors as $error) {
                echo "- $error\n";
            }
        } else {
            echo "\nNo validation errors found.\n";
        }
    }
}
