<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia;

use Nette\Neon\Neon;

class ConfigLoader
{
    /**
     * @return array<string, mixed>
     */
    public static function load(?string $configFile, string $defaultConfigFileName): array
    {
        $projectRoot = self::findProjectRoot();
        $defaultConfigFile = $projectRoot . DIRECTORY_SEPARATOR . $defaultConfigFileName;

        if (null !== $configFile) {
            if (!file_exists($configFile)) {
                echo "Error: Configuration file '$configFile' not found.\n";
                exit(1);
            }
            $content = file_get_contents($configFile);
            /** @var array<string, mixed> $config */
            $config = Neon::decode((string)$content);
            if (empty($config)) {
                echo "Error: Configuration file '$configFile' is empty or not parsable.\n";
                exit(1);
            }
            return $config;
        }

        if (file_exists($defaultConfigFile)) {
            /** @var array<string, mixed> $config */
            $config = Neon::decode((string)file_get_contents($defaultConfigFile));
            return $config;
        }

        return self::interactiveSetup($defaultConfigFile);
    }

    /**
     * @param string[] $argv
     */
    public static function getVerbosity(array $argv): int
    {
        $verbosity = 0;
        foreach ($argv as $arg) {
            if ('-v' === $arg) {
                $verbosity = 1;
            } elseif ('-vv' === $arg) {
                $verbosity = 2;
            }
        }
        return $verbosity;
    }

    /**
     * @param string[] $argv
     */
    public static function getConfigFile(array $argv): ?string
    {
        $count = \count($argv);
        for ($i = 0; $i < $count; $i++) {
            if ('-f' === $argv[$i]) {
                if (isset($argv[$i + 1])) {
                    return $argv[$i + 1];
                }
                echo "Error: -f flag requires a file path.\n";
                exit(1);
            }
        }
        return null;
    }


    /**
     * @return array<string, mixed>
     */
    private static function interactiveSetup(string $defaultConfigFile): array
    {
        $projectRoot = \dirname($defaultConfigFile);
        echo "No configuration file found in $projectRoot. Let's create one.\n";

        echo 'Source directories (comma separated) [src]: ';
        $srcDirInput = trim((string)fgets(STDIN)) ?: 'src';
        $srcDirs = array_map('trim', explode(',', $srcDirInput));

        echo 'Exception attribute [ExceptionReason]: ';
        $attr = trim((string)fgets(STDIN)) ?: 'ExceptionReason';

        echo 'Output directory [./throwpedia]: ';
        $outDir = trim((string)fgets(STDIN)) ?: './throwpedia';

        echo 'Allow direct new Exceptions? (y/n) [n]: ';
        $allowNewInput = strtolower(trim((string)fgets(STDIN)));
        $allowNew = ('y' === $allowNewInput || 'yes' === $allowNewInput) ? 'true' : 'false';

        $sourceNeon = "source:\n";
        foreach ($srcDirs as $dir) {
            $sourceNeon .= "    - $dir\n";
        }

        $neonContent = <<<NEON
            # Configuration for throwpedia

            # Sources
            $sourceNeon
            
            # Exception Attributes
            attributes:
                - $attr

            # Output files. Remove the ones that you do not need
            outputs:
                - $outDir/exceptions.json
                - $outDir/exceptions.yaml
                - $outDir/exceptions.md

            # Is throw new Exception() allowed or is only throw MyException::Method() allowed
            allowDirectNew: $allowNew
            NEON;

        file_put_contents($defaultConfigFile, $neonContent);
        echo "Created $defaultConfigFile\n";
        /** @var array<string, mixed> $config */
        $config = Neon::decode($neonContent);
        return $config;
    }
    public static function findProjectRoot(): string
    {
        // If we are in vendor/tetrode/throwpedia, the root is 3 levels up.
        // But we should be more robust.
        $dir = __DIR__;
        while ($dir !== DIRECTORY_SEPARATOR && !file_exists($dir . DIRECTORY_SEPARATOR . 'composer.json')) {
            $parent = \dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        // If we found a composer.json, check if it's our own or the project root.
        // If we are in vendor, the project root will have a composer.json and a vendor directory.
        if (file_exists($dir . DIRECTORY_SEPARATOR . 'vendor/tetrode/throwpedia')) {
            return $dir;
        }

        // If we are running from the tool's own root (e.g. during development)
        return \getcwd() ?: $dir;
    }
}
