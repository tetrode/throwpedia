<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia;

use Nette\Neon\Neon;
use Tetrode\Throwpedia\Attributes\ExceptionReason;
use Tetrode\Throwpedia\DTO\OutputTarget;
use Tetrode\Throwpedia\DTO\ThrowpediaConfig;
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
     */
    #[ExceptionReason('load', 'configuration file not found', 'configuration file is missing')]
    #[ExceptionReason('load', 'configuration file not parsable', 'configuration file is not parsable or empty')]
    public function load(?string $configFile, string $defaultConfigFileName): ThrowpediaConfig
    {
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
            return $this->createConfig($config, \dirname(realpath($configFile)));
        }

        $projectRoot = $this->findProjectRoot();
        $defaultConfigFile = $projectRoot . DIRECTORY_SEPARATOR . $defaultConfigFileName;

        if (file_exists($defaultConfigFile)) {
            /** @var array<string, mixed> $config */
            $config = Neon::decode((string)file_get_contents($defaultConfigFile));
            return $this->createConfig($config, \dirname(realpath($defaultConfigFile)));
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
     */
    private function interactiveSetup(string $defaultConfigFile): ThrowpediaConfig
    {
        $projectRoot = \dirname($defaultConfigFile);
        $this->output->writeln("No configuration file found in $projectRoot. Let's create one.");

        $this->output->write('Source directories (comma separated) [src]: ');
        $srcDirInput = trim((string)fgets($this->inputStream)) ?: 'src';
        $srcDirs = array_map('trim', explode(',', $srcDirInput));

        $this->output->write('Exception attributes (comma separated) [ExceptionReason]: ');
        $attrInput = trim((string)fgets($this->inputStream)) ?: 'ExceptionReason';
        $attrs = array_map('trim', explode(',', $attrInput));

        $this->output->write('Output directory [./throwpedia]: ');
        $outDir = trim((string)fgets($this->inputStream)) ?: './throwpedia';

        $this->output->write('Allow direct new Exceptions? (y/n) [n]: ');
        $allowNewInput = strtolower(trim((string)fgets($this->inputStream)));
        $allowNew = ('y' === $allowNewInput || 'yes' === $allowNewInput);
        $allowNewStr = $allowNew ? 'true' : 'false';

        $sourceNeon = "source:\n";
        foreach ($srcDirs as $dir) {
            $sourceNeon .= "    - $dir\n";
        }

        $attrNeon = "attributes:\n";
        foreach ($attrs as $attr) {
            $attrNeon .= "    - $attr\n";
        }

        $neonContent = <<<NEON
            # Configuration for throwpedia

            # Sources
            $sourceNeon
            
            # Exception Attributes
            # You can provide a simple list of attribute names OR a map with specific fields.
            $attrNeon

            # Default fields for attributes (optional)
            # These are used by default for all attributes listed above.
            fields:
                identifier: Identifier
                technicalReason: Technical Reason
                businessReason: Business Reason

            # Output files. Remove the ones that you do not need
            outputs:
                - $outDir/exceptions.json
                - $outDir/exceptions.yaml
                - $outDir/exceptions.md

            # Is throw MyException::Method() required? (false means direct 'new' is not allowed)
            allowDirectNew: $allowNewStr

            # Suppress duplicate identifier warnings (optional)
            suppressDuplicateIdentifierWarning: false
            NEON;

        file_put_contents($defaultConfigFile, $neonContent);
        $this->output->writeln("Created $defaultConfigFile");
        /** @var array<string, mixed> $config */
        $config = Neon::decode($neonContent);
        return $this->createConfig($config, $projectRoot);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createConfig(array $config, string $projectRoot): ThrowpediaConfig
    {
        $outputs = [];
        foreach ((array)($config['outputs'] ?? []) as $outputPath) {
            $extension = pathinfo((string)$outputPath, PATHINFO_EXTENSION);
            $outputs[] = new OutputTarget((string)$outputPath, $extension);
        }

        $defaultFields = [];
        if (isset($config['fields'])) {
            foreach ((array)$config['fields'] as $name => $label) {
                $defaultFields[] = new DTO\AttributeField((string)$name, (string)$label, 'identifier' === $name);
            }
        } else {
            $defaultFields = [
                new DTO\AttributeField('identifier', 'Identifier', true),
                new DTO\AttributeField('technicalReason', 'Technical Reason'),
                new DTO\AttributeField('businessReason', 'Business Reason'),
            ];
        }

        $attributeFields = [];
        $attributesConfig = (array)($config['attributes'] ?? ['ExceptionReason']);

        foreach ($attributesConfig as $key => $val) {
            if (\is_string($key)) {
                $attrName = $key;
                $fields = [];
                foreach ((array)$val as $fName => $fLabel) {
                    $fields[] = new DTO\AttributeField((string)$fName, (string)$fLabel, 'identifier' === $fName);
                }
                $attributeFields[$attrName] = $fields;
            } else {
                $attrName = (string)$val;
                $attributeFields[$attrName] = $defaultFields;
            }
        }

        return new ThrowpediaConfig(
            sources: (array)($config['source'] ?? ['src']),
            attributeFields: $attributeFields,
            outputs: $outputs,
            allowDirectNew: (bool)($config['allowDirectNew'] ?? false),
            suppressDuplicateIdentifierWarning: (bool)($config['suppressDuplicateIdentifierWarning'] ?? false),
            projectRoot: $projectRoot
        );
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
