<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia\Tests;

use PHPUnit\Framework\TestCase;
use Tetrode\Throwpedia\Analyzer;
use Tetrode\Throwpedia\IO\NullOutput;

class AnalyzerTest extends TestCase
{
    private string $tempFile;
    private NullOutput $output;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'tp_test_');
        $this->output = new NullOutput();
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testAnalyzeAndBuildModel(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class MyClass {
    #[ExceptionReason(code: 'ERR_1', technical: 'Tech 1', business: 'Biz 1')]
    public function method1() {
        throw MyException::error();
    }
}
PHP;
        file_put_contents($this->tempFile, $code);

        $analyzer = new Analyzer($this->output);
        $model = $analyzer->analyze([$this->tempFile]);

        $this->assertArrayHasKey('ERR_1', $model);
        $this->assertEquals('Biz 1', $model['ERR_1']['business']);
        $this->assertEquals('Tech 1', $model['ERR_1']['technical']);
        $this->assertEquals('App\MyException::error', $model['ERR_1']['exception']);
        $this->assertEquals(['App\MyClass::method1'], $model['ERR_1']['thrown_from']);
    }

    public function testValidationErrorsForMissingAttribute(): void
    {
        $code = <<<'PHP'
<?php
class MyClass {
    public function methodWithoutAttr() {
        throw MyException::error();
    }
}
PHP;
        file_put_contents($this->tempFile, $code);

        $analyzer = new Analyzer($this->output);
        $analyzer->analyze([$this->tempFile]);
        $errors = $analyzer->getValidationErrors();

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Missing #[ExceptionReason]', $errors[0]);
    }

    public function testValidationErrorsForDirectNew(): void
    {
        $code = <<<'PHP'
<?php
class MyClass {
    public function methodWithDirectNew() {
        throw new \Exception('Oops');
    }
}
PHP;
        file_put_contents($this->tempFile, $code);

        $analyzer = new Analyzer($this->output, ['allowDirectNew' => false]);
        $analyzer->analyze([$this->tempFile]);
        $errors = $analyzer->getValidationErrors();

        $this->assertCount(1, $errors);
        $this->assertStringContainsString("Direct 'new' usage", $errors[0]);
    }

    public function testAllowDirectNewInModel(): void
    {
        $code = <<<'PHP'
<?php
class MyClass {
    public function methodWithDirectNew() {
        throw new \Exception('Oops');
    }
}
PHP;
        file_put_contents($this->tempFile, $code);

        $analyzer = new Analyzer($this->output, ['allowDirectNew' => true]);
        $model = $analyzer->analyze([$this->tempFile]);

        $this->assertArrayHasKey('DIRECT_NEW_EXCEPTION', $model);
        $this->assertEquals('Exception', $model['DIRECT_NEW_EXCEPTION']['exception']);
    }
}
