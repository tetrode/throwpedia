<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia\Tests;

use PHPUnit\Framework\TestCase;
use Tetrode\Throwpedia\IO\BufferedOutput;
use Tetrode\Throwpedia\Throwpedia;

class ThrowpediaTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tp_integration_' . uniqid();
        mkdir($this->tempDir);
        mkdir($this->tempDir . '/src');
        file_put_contents($this->tempDir . '/composer.json', '{}');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testRunSmokeTest(): void
    {
        $sourcecode = <<<'PHP'
            <?php
            class TestClass {
                #[ExceptionReason(identifier: 'CODE1', technicalReason: 'Tech', businessReason: 'Biz')]
                public function test() {
                    throw MyEx::fail();
                }
            }
            PHP;
        file_put_contents($this->tempDir . '/src/TestClass.php', $sourcecode);

        $config = <<<'NEON'
            source:
                - src
            outputs:
                - output.json
            NEON;
        file_put_contents($this->tempDir . '/throwpedia.neon', $config);

        // We need to change CWD because ConfigLoader::findProjectRoot and collectFiles use it or relative paths
        $oldCwd = getcwd();
        $this->assertIsString($oldCwd);
        chdir($this->tempDir);

        try {
            $bufferedOutput = new BufferedOutput();
            $throwpedia = new Throwpedia($bufferedOutput);
            $throwpedia->run(['bin/throwpedia', '-f', 'throwpedia.neon']);
            $output = $bufferedOutput->fetch();

            $this->assertStringContainsString('Analysis complete.', $output);
            $this->assertFileExists($this->tempDir . '/output.json');

            $contents = file_get_contents($this->tempDir . '/output.json');
            $this->assertIsString($contents);
            $json = json_decode($contents, true);
            $this->assertArrayHasKey('entries', $json);
            $this->assertArrayHasKey('ExceptionReason:CODE1', $json['entries']);
        } finally {
            chdir($oldCwd);
        }
    }

    public function testSourceNotFoundWarning(): void
    {
        $config = <<<'NEON'
            source:
                - non_existent_dir
            NEON;
        file_put_contents($this->tempDir . '/throwpedia.neon', $config);
        $oldCwd = getcwd();
        $this->assertIsString($oldCwd);
        chdir($this->tempDir);

        try {
            $bufferedOutput = new BufferedOutput();
            $throwpedia = new Throwpedia($bufferedOutput);
            $throwpedia->run(['bin/throwpedia', '-f', 'throwpedia.neon']);

            $this->assertStringContainsString('Warning: Source directory or file', $bufferedOutput->fetch());
        } finally {
            chdir($oldCwd);
        }
    }

    public function testUnknownOutputExtensionWarning(): void
    {
        $config = <<<'NEON'
            source:
                - src
            outputs:
                - output.unknown
            NEON;
        file_put_contents($this->tempDir . '/throwpedia.neon', $config);
        $oldCwd = getcwd();
        $this->assertIsString($oldCwd);
        chdir($this->tempDir);

        try {
            $bufferedOutput = new BufferedOutput();
            $throwpedia = new Throwpedia($bufferedOutput);
            $throwpedia->run(['bin/throwpedia', '-f', 'throwpedia.neon']);

            $this->assertStringContainsString("Warning: Unknown output extension 'unknown'", $bufferedOutput->fetch());
        } finally {
            chdir($oldCwd);
        }
    }

    public function testRecursiveCollection(): void
    {
        mkdir($this->tempDir . '/src/SubDir');
        $sourcecode = "<?php class SubClass { #[ExceptionReason(identifier: 'SUB', technicalReason: 'T', businessReason: 'B')] public function m() { throw E::e(); } }";
        file_put_contents($this->tempDir . '/src/SubDir/SubClass.php', $sourcecode);

        $config = <<<'NEON'
            source:
                - src
            outputs:
                - out.json
            NEON;
        file_put_contents($this->tempDir . '/throwpedia.neon', $config);
        $oldCwd = getcwd();
        $this->assertIsString($oldCwd);
        chdir($this->tempDir);

        try {
            $bufferedOutput = new BufferedOutput();
            $throwpedia = new Throwpedia($bufferedOutput);
            $throwpedia->run(['bin/throwpedia', '-f', 'throwpedia.neon']);

            $this->assertFileExists($this->tempDir . '/out.json');
            $contents = file_get_contents($this->tempDir . '/out.json');
            $this->assertIsString($contents);
            $json = json_decode($contents, true);
            $this->assertArrayHasKey('entries', $json);
            $this->assertArrayHasKey('ExceptionReason:SUB', $json['entries']);
        } finally {
            chdir($oldCwd);
        }
    }

    public function testSingleFileSource(): void
    {
        $sourcecode = "<?php class Single { #[ExceptionReason(identifier: 'SINGLE', technicalReason: 'T', businessReason: 'B')] public function m() { throw E::e(); } }";
        file_put_contents($this->tempDir . '/Single.php', $sourcecode);

        $config = <<<'NEON'
            source:
                - Single.php
            outputs:
                - single.json
            NEON;
        file_put_contents($this->tempDir . '/throwpedia.neon', $config);
        $oldCwd = getcwd();
        $this->assertIsString($oldCwd);
        chdir($this->tempDir);

        try {
            $bufferedOutput = new BufferedOutput();
            $throwpedia = new Throwpedia($bufferedOutput);
            $throwpedia->run(['bin/throwpedia', '-f', 'throwpedia.neon']);

            $contents = file_get_contents($this->tempDir . '/single.json');
            $this->assertIsString($contents);
            $json = json_decode($contents, true);
            $this->assertArrayHasKey('entries', $json);
            $this->assertArrayHasKey('ExceptionReason:SINGLE', $json['entries']);
        } finally {
            chdir($oldCwd);
        }
    }

    public function testNestedOutputDirectory(): void
    {
        $config = <<<'NEON'
            source:
                - src
            outputs:
                - nested/dir/output.json
            NEON;
        file_put_contents($this->tempDir . '/throwpedia.neon', $config);
        $oldCwd = getcwd();
        $this->assertIsString($oldCwd);
        chdir($this->tempDir);

        try {
            $bufferedOutput = new BufferedOutput();
            $throwpedia = new Throwpedia($bufferedOutput);
            $throwpedia->run(['bin/throwpedia', '-f', 'throwpedia.neon']);

            $this->assertDirectoryExists($this->tempDir . '/nested/dir');
            $this->assertFileExists($this->tempDir . '/nested/dir/output.json');
        } finally {
            chdir($oldCwd);
        }
    }

    public function testCaseInsensitiveExtensionMatchingInRender(): void
    {
        $sourcecode = "<?php class Test { #[ExceptionReason(identifier: 'UPPER', technicalReason: 'T', businessReason: 'B')] public function m() { throw E::e(); } }";
        file_put_contents($this->tempDir . '/src/Test.php', $sourcecode);

        $config = <<<'NEON'
            source:
                - src
            outputs:
                - output.JSON
            NEON;
        file_put_contents($this->tempDir . '/throwpedia.neon', $config);
        $oldCwd = getcwd();
        $this->assertIsString($oldCwd);
        chdir($this->tempDir);

        try {
            $bufferedOutput = new BufferedOutput();
            $throwpedia = new Throwpedia($bufferedOutput);
            $throwpedia->run(['bin/throwpedia', '-f', 'throwpedia.neon']);

            $this->assertFileExists($this->tempDir . '/output.JSON');
        } finally {
            chdir($oldCwd);
        }
    }
}
