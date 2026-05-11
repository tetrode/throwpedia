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

    public function testGetVerbosity(): void
    {
        $this->assertEquals(0, $this->loader->getVerbosity(['bin/throwpedia']));
        $this->assertEquals(1, $this->loader->getVerbosity(['bin/throwpedia', '-v']));
        $this->assertEquals(2, $this->loader->getVerbosity(['bin/throwpedia', '-vv']));
        $this->assertEquals(1, $this->loader->getVerbosity(['bin/throwpedia', '-v', '--other']));
    }

    public function testGetConfigFile(): void
    {
        $this->assertNull($this->loader->getConfigFile(['bin/throwpedia']));
        $this->assertEquals('config.neon', $this->loader->getConfigFile(['bin/throwpedia', '-f', 'config.neon']));
    }

    public function testFindProjectRoot(): void
    {
        $root = $this->loader->findProjectRoot();
        $this->assertDirectoryExists($root);
        $this->assertFileExists($root . DIRECTORY_SEPARATOR . 'composer.json');
    }
}
