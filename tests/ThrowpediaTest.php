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
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testRunSmokeTest(): void
    {
        $code = <<<'PHP'
<?php
class TestClass {
    #[ExceptionReason('CODE1', 'Tech', 'Biz')]
    public function test() {
        throw MyEx::fail();
    }
}
PHP;
        file_put_contents($this->tempDir . '/src/TestClass.php', $code);
        
        $config = <<<'NEON'
source:
    - src
outputs:
    - output.json
NEON;
        file_put_contents($this->tempDir . '/throwpedia.neon', $config);

        // We need to change CWD because ConfigLoader::findProjectRoot and collectFiles use it or relative paths
        $oldCwd = getcwd();
        chdir($this->tempDir);

        try {
            $bufferedOutput = new BufferedOutput();
            $throwpedia = new Throwpedia($bufferedOutput);
            $throwpedia->run(['bin/throwpedia', '-f', 'throwpedia.neon']);
            $output = $bufferedOutput->fetch();

            $this->assertStringContainsString('Analysis complete.', $output);
            $this->assertFileExists($this->tempDir . '/output.json');
            
            $json = json_decode(file_get_contents($this->tempDir . '/output.json'), true);
            $this->assertArrayHasKey('CODE1', $json);
        } finally {
            chdir($oldCwd);
        }
    }
}
