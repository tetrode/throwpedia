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

    public function testAnalyzeWithMultipleAttributesOnSameMethod(): void
    {
        $code = <<<'PHP'
<?php
class MyClass {
    #[ExceptionReason(code: 'ERR_A', technical: 'Tech A', business: 'Biz A')]
    #[ExceptionReason(code: 'ERR_B', technical: 'Tech B', business: 'Biz B')]
    public function methodWithTwoAttributes() {
        throw MyException::error();
    }
}
PHP;
        file_put_contents($this->tempFile, $code);

        $analyzer = new Analyzer($this->output);
        $model = $analyzer->analyze([$this->tempFile]);

        $this->assertCount(2, $model);
        $this->assertArrayHasKey('ERR_A', $model);
        $this->assertArrayHasKey('ERR_B', $model);

        $this->assertEquals('Biz A', $model['ERR_A']['business']);
        $this->assertEquals(['MyClass::methodWithTwoAttributes'], $model['ERR_A']['thrown_from']);

        $this->assertEquals('Biz B', $model['ERR_B']['business']);
        $this->assertEquals(['MyClass::methodWithTwoAttributes'], $model['ERR_B']['thrown_from']);
    }

    public function testAnalyzeWithDuplicateAttributeCodesOnSameMethod(): void
    {
        $code = <<<'PHP'
<?php
class MyClass {
    #[ExceptionReason(code: 'DUP', technical: 'Tech 1', business: 'Biz 1')]
    #[ExceptionReason(code: 'DUP', technical: 'Tech 2', business: 'Biz 2')]
    public function methodWithDuplicateCodes() {
        throw MyException::error();
    }
}
PHP;
        file_put_contents($this->tempFile, $code);

        $analyzer = new Analyzer($this->output);
        $model = $analyzer->analyze([$this->tempFile]);

        // It should probably just have one entry for 'DUP'
        $this->assertCount(1, $model);
        $this->assertArrayHasKey('DUP', $model);
        // The first one wins based on current implementation
        $this->assertEquals('Biz 1', $model['DUP']['business']);
        $this->assertEquals(['MyClass::methodWithDuplicateCodes'], $model['DUP']['thrown_from']);
    }

    public function testAnalyzeWithSameCodeOnDifferentMethods(): void
    {
        $code = <<<'PHP'
<?php
class ClassA {
    #[ExceptionReason(code: 'SHARED', technical: 'Tech', business: 'Biz')]
    public function methodA() { throw E::e(); }
}
class ClassB {
    #[ExceptionReason(code: 'SHARED', technical: 'Tech', business: 'Biz')]
    public function methodB() { throw E::e(); }
}
PHP;
        file_put_contents($this->tempFile, $code);

        $analyzer = new Analyzer($this->output);
        $model = $analyzer->analyze([$this->tempFile]);

        $this->assertCount(1, $model);
        $this->assertArrayHasKey('SHARED', $model);
        $this->assertCount(2, $model['SHARED']['thrown_from']);
        $this->assertContains('ClassA::methodA', $model['SHARED']['thrown_from']);
        $this->assertContains('ClassB::methodB', $model['SHARED']['thrown_from']);
    }

    public function testAnalyzeWithMultipleAttributesAndMultipleThrows(): void
    {
        $code = <<<'PHP'
<?php
class MyClass {
    #[ExceptionReason(code: 'E1', technical: 'T1', business: 'B1')]
    #[ExceptionReason(code: 'E2', technical: 'T2', business: 'B2')]
    public function multipleBoth() {
        if (rand(0,1)) {
             throw Exc1::error();
        }
        throw Exc2::error();
    }
}
PHP;
        file_put_contents($this->tempFile, $code);

        $analyzer = new Analyzer($this->output);
        $model = $analyzer->analyze([$this->tempFile]);

        $this->assertCount(2, $model);
        // Currently, it just takes the first exception found in the method for all its attributes
        $this->assertEquals('Exc1::error', $model['E1']['exception']);
        $this->assertEquals('Exc1::error', $model['E2']['exception']);
    }
}
