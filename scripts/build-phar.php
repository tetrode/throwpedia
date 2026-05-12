<?php

declare(strict_types=1);

$projectRoot = \dirname(__DIR__);
$pharFile = $projectRoot . '/throwpedia.phar';

if (file_exists($pharFile)) {
    unlink($pharFile);
}

$phar = new Phar($pharFile);

$phar->startBuffering();

/**
 * Create iterator
 */
$directory = new RecursiveDirectoryIterator(
    $projectRoot,
    FilesystemIterator::SKIP_DOTS
);

$iterator = new RecursiveIteratorIterator($directory);

/**
 * Filter files
 */
$filter = new CallbackFilterIterator(
    $iterator,
    static function (SplFileInfo $file): bool {
        $path = $file->getRealPath();

        // exclude these directories
        $excludedDirs = ['.git', '.idea', '.phpunit.cache', 'examples', 'output', 'scripts', 'tests'];
        foreach ($excludedDirs as $dir) {
            if (str_contains($path, DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR)) {
                return false;
            }
        }

        // exclude these files
        $excludedFiles = ['composer-require-checker.json','.php-cs-fixer.php'];
        if (\in_array($file->getFilename(), $excludedFiles, true)) {
            return false;
        }

        // Include the CLI entry point
        if (str_ends_with($path, DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'throwpedia')) {
            return true;
        }

        // Include only these file types
        $extension = $file->getExtension();
        if (\in_array($extension, ['php'], true)) {
            return true;
        }

        return false;
    }
);

// Construct a phar archive from an iterator.
$phar->buildFromIterator($filter, $projectRoot);

// Stub
$stub = <<<'PHP'
    #!/usr/bin/env php
    <?php

    Phar::mapPhar('throwpedia.phar');

    require 'phar://throwpedia.phar/bin/throwpedia';

    __HALT_COMPILER();
    PHP;

// set the PHP loader or bootstrap stub of a Phar archive
$phar->setStub($stub);

// Compresses all files in the current Phar archive
$phar->compressFiles(Phar::GZ);

// Stop buffering write requests to the Phar archive, and save changes to disk
$phar->stopBuffering();

echo "PHAR created\n";
