<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia;

use Nette\Neon\Neon;
use Tetrode\Throwpedia\Attributes\ExceptionReason;
use Tetrode\Throwpedia\Exception\ConfigurationException;
use Tetrode\Throwpedia\IO\OutputInterface;

class ConfigLoader
{
    /** @var resource */
    private $inputStream;

    /**
     * @param resource|null $inputStream
     */
    public function __construct(
        private readonly OutputInterface $output,
        $inputStream = null
    ) {
        $this->inputStream = $inputStream ?? STDIN;
    }

    /**
     * @return array<string, mixed>
     */
    #[ExceptionReason('load', 'configuration file not found', 'configuration file is missing')]
    #[ExceptionReason('load', 'configuration file not parsable', 'configuration file is not parsable or empty')]
    public function load(?string $configFile, string $defaultConfigFileName): array
    {
        $projectRoot = $this->findProjectRoot();
        $defaultConfigFile = $projectRoot . DIRECTORY_SEPARATOR . $defaultConfigFileName;

        if (null !== $configFile) {
            if (!file_exists($configFile)) {
                throw ConfigurationException::FileNotFound($configFile);
            }
            $content = file_get_contents($configFile);
            /** @var array<string, mixed> $config */
            $config = Neon::decode((string)$content);
            if (empty($config)) {
                throw ConfigurationException::FileNotParsable($configFile);
            }
            return $config;
        }

        if (file_exists($defaultConfigFile)) {
            /** @var array<string, mixed> $config */
            $config = Neon::decode((string)file_get_contents($defaultConfigFile));
            return $config;
        }

        return $this->interactiveSetup($defaultConfigFile);
    }

    /**
     * @param string[] $argv
     */
    #[ExceptionReason('load', 'configuration file not found', 'configuration file is missing.')]
    public function getConfigFile(array $argv): ?string
    {
        $count = \count($argv);
        for ($i = 0; $i < $count; $i++) {
            if ('-f' === $argv[$i]) {
                if (isset($argv[$i + 1])) {
                    return $argv[$i + 1];
                }
                throw ConfigurationException::FilePathNotGiven();
            }
        }
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function interactiveSetup(string $defaultConfigFile): array
    {
        $projectRoot = \dirname($defaultConfigFile);
        $this->output->writeln("No configuration file found in $projectRoot. Let's create one.");

        $this->output->write('Source directories (comma separated) [src]: ');
        $srcDirInput = trim((string)fgets($this->inputStream)) ?: 'src';
        $srcDirs = array_map('trim', explode(',', $srcDirInput));

        $this->output->write('Exception attribute [ExceptionReason]: ');
        $attr = trim((string)fgets($this->inputStream)) ?: 'ExceptionReason';

        $this->output->write('Output directory [./throwpedia]: ');
        $outDir = trim((string)fgets($this->inputStream)) ?: './throwpedia';

        $this->output->write('Allow direct new Exceptions? (y/n) [n]: ');
        $allowNewInput = strtolower(trim((string)fgets($this->inputStream)));
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
        $this->output->writeln("Created $defaultConfigFile");
        /** @var array<string, mixed> $config */
        $config = Neon::decode($neonContent);
        return $config;
    }

    public function findProjectRoot(): string
    {
        $cwd = getcwd();
        if (false !== $cwd && file_exists($cwd . DIRECTORY_SEPARATOR . 'composer.json')) {
            return $cwd;
        }

        $dir = __DIR__;
        $lastFound = null;
        while (DIRECTORY_SEPARATOR !== $dir) {
            if (file_exists($dir . DIRECTORY_SEPARATOR . 'composer.json')) {
                if (file_exists($dir . DIRECTORY_SEPARATOR . 'vendor/tetrode/throwpedia')) {
                    return $dir;
                }
                $lastFound = $dir;
            }
            $parent = \dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        return $cwd ?: ($lastFound ?? $dir);
    }
}
