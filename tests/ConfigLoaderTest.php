<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia\Tests;

use PHPUnit\Framework\TestCase;
use Tetrode\Throwpedia\ConfigLoader;
use Tetrode\Throwpedia\IO\NullOutput;

class ConfigLoaderTest extends TestCase
{
    private ConfigLoader $loader;

    protected function setUp(): void
    {
        $this->loader = new ConfigLoader(new NullOutput());
    }

    public function testGetConfigFile(): void
    {
        $this->assertNull($this->loader->getConfigFile(['bin/throwpedia']));
        $this->assertEquals('config.neon', $this->loader->getConfigFile(['bin/throwpedia', '-f', 'config.neon']));
    }

    public function testGetConfigFileMissingPath(): void
    {
        $this->expectException(\Tetrode\Throwpedia\Exception\ConfigurationException::class);
        $this->expectExceptionMessage('-f requires a file path');
        $this->loader->getConfigFile(['bin/throwpedia', '-f']);
    }

    public function testFindProjectRoot(): void
    {
        $root = $this->loader->findProjectRoot();
        $this->assertDirectoryExists($root);
        $this->assertFileExists($root . DIRECTORY_SEPARATOR . 'composer.json');
    }

    public function testLoadExplicitFileNotFound(): void
    {
        $this->expectException(\Tetrode\Throwpedia\Exception\ConfigurationException::class);
        $this->expectExceptionMessage("Configuration file 'non-existent.neon' not found.");
        $this->loader->load('non-existent.neon', 'default.neon');
    }

    public function testLoadExplicitFileEmpty(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'tp_empty_');
        file_put_contents($tempFile, '');

        try {
            $this->expectException(\Tetrode\Throwpedia\Exception\ConfigurationException::class);
            $this->expectExceptionMessage("Configuration file '$tempFile' is empty or not parsable.");
            $this->loader->load($tempFile, 'default.neon');
        } finally {
            unlink($tempFile);
        }
    }

    public function testInteractiveSetup(): void
    {
        $input = "src_dir1, src_dir2\nMyException, OtherException\n./out\ny\n";
        $inputStream = fopen('php://memory', 'r+');
        $this->assertIsResource($inputStream);
        fwrite($inputStream, $input);
        rewind($inputStream);

        $tempDefaultConfigName = 'tp_default_' . uniqid() . '.neon';
        $loader = new ConfigLoader(new NullOutput(), $inputStream);

        try {
            $config = $loader->load(null, $tempDefaultConfigName);

            $this->assertEquals(['src_dir1', 'src_dir2'], $config->sources);
            $this->assertEquals(['MyException', 'OtherException'], array_keys($config->attributeFields));
            $this->assertTrue($config->allowDirectNew);

            $outputPaths = array_map(fn ($o) => $o->path, $config->outputs);
            $this->assertContains('./out/exceptions.json', $outputPaths);

            $projectRoot = $loader->findProjectRoot();
            $expectedFile = $projectRoot . DIRECTORY_SEPARATOR . $tempDefaultConfigName;
            $this->assertFileExists($expectedFile);
            unlink($expectedFile);
        } finally {
            fclose($inputStream);
        }
    }
}
