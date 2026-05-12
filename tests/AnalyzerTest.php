<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia\Tests;

use PHPUnit\Framework\TestCase;
use Tetrode\Throwpedia\Analyzer;
use Tetrode\Throwpedia\DTO\AnalyzerConfig;
use Tetrode\Throwpedia\DTO\ValidationIssue;
use Tetrode\Throwpedia\IO\BufferedOutput;
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
        $sourcecode = <<<'PHP'
            <?php
            namespace App;
            class MyClass {
                #[ExceptionReason(identifier: 'ERR_1', technicalReason: 'Tech 1', businessReason: 'Biz 1')]
                public function method1() {
                    throw MyException::error();
                }
            }
            PHP;
        file_put_contents($this->tempFile, $sourcecode);

        $analyzer = new Analyzer($this->output);
        $catalog = $analyzer->analyze([$this->tempFile]);
        $model = $catalog->entries;

        $this->assertArrayHasKey('ExceptionReason:ERR_1', $model);
        $this->assertEquals('Biz 1', $model['ExceptionReason:ERR_1']->values['businessReason']);
        $this->assertEquals('Tech 1', $model['ExceptionReason:ERR_1']->values['technicalReason']);
        $this->assertEquals('App\MyException::error', $model['ExceptionReason:ERR_1']->exception);
        $this->assertEquals(['App\MyClass::method1:6'], $model['ExceptionReason:ERR_1']->thrown_from);
    }

    public function testValidationErrorsForMissingAttribute(): void
    {
        $sourcecode = <<<'PHP'
            <?php
            class MyClass {
                public function methodWithoutAttr() {
                    throw MyException::error();
                }
            }
            PHP;
        file_put_contents($this->tempFile, $sourcecode);

        $analyzer = new Analyzer($this->output);
        $analyzer->analyze([$this->tempFile]);
        $issues = $analyzer->getValidationIssues();

        $this->assertCount(1, $issues);
        $this->assertStringContainsString('Missing #[ExceptionReason]', $issues[0]->message);
    }

    public function testValidationErrorsForDirectNew(): void
    {
        $sourcecode = <<<'PHP'
            <?php
            class MyClass {
                public function methodWithDirectNew() {
                    throw new \Exception('Oops');
                }
            }
            PHP;
        file_put_contents($this->tempFile, $sourcecode);

        $analyzer = new Analyzer($this->output, new AnalyzerConfig(allowDirectNew: false));
        $analyzer->analyze([$this->tempFile]);
        $issues = $analyzer->getValidationIssues();

        $this->assertCount(2, $issues);
        $this->assertStringContainsString("Direct 'new' usage", $issues[0]->message);
        $this->assertStringContainsString('Missing #[ExceptionReason]', $issues[1]->message);
    }

    public function testAllowDirectNewInModel(): void
    {
        $sourcecode = <<<'PHP'
            <?php
            class MyClass {
                public function methodWithDirectNew() {
                    throw new \Exception('Oops');
                }
            }
            PHP;
        file_put_contents($this->tempFile, $sourcecode);

        $analyzer = new Analyzer($this->output, new AnalyzerConfig(allowDirectNew: true));
        $catalog = $analyzer->analyze([$this->tempFile]);
        $model = $catalog->entries;

        $this->assertArrayHasKey('ExceptionReason:DIRECT_NEW_EXCEPTION', $model);
        $this->assertEquals('Exception', $model['ExceptionReason:DIRECT_NEW_EXCEPTION']->exception);
    }

    public function testAnalyzeHandlesParseError(): void
    {
        $sourcecode = '<?php invalid syntax here';
        file_put_contents($this->tempFile, $sourcecode);

        $bufferedOutput = new BufferedOutput();
        $analyzer = new Analyzer($bufferedOutput);
        $analyzer->analyze([$this->tempFile]);

        $this->assertStringContainsString('Parse Error', $bufferedOutput->fetch());
    }

    public function testAnalyzeHandlesMissingFile(): void
    {
        $analyzer = new Analyzer($this->output);
        // This should not throw an exception but just continue
        $catalog = $analyzer->analyze(['non-existent-file.php']);
        $this->assertEmpty($catalog->entries);
    }

    public function testAppendDirectNewThrowsWithDuplicates(): void
    {
        $sourcecode = <<<'PHP'
            <?php
            class MyClass {
                public function m1() { throw new \Exception('1'); }
                public function m2() { throw new \Exception('2'); }
            }
            PHP;
        file_put_contents($this->tempFile, $sourcecode);
        $analyzer = new Analyzer($this->output, new AnalyzerConfig(allowDirectNew: true));
        $catalog = $analyzer->analyze([$this->tempFile]);
        $model = $catalog->entries;

        $this->assertArrayHasKey('ExceptionReason:DIRECT_NEW_EXCEPTION', $model);
        $this->assertArrayHasKey('ExceptionReason:DIRECT_NEW_EXCEPTION_1', $model);
    }

    public function testAnalyzeWithMultipleAttributesOnSameMethod(): void
    {
        $sourcecode = <<<'PHP'
            <?php
            class MyClass {
                #[ExceptionReason(identifier: 'ERR_A', technicalReason: 'Tech A', businessReason: 'Biz A')]
                #[ExceptionReason(identifier: 'ERR_B', technicalReason: 'Tech B', businessReason: 'Biz B')]
                public function methodWithTwoAttributes() {
                    throw MyException::error();
                }
            }
            PHP;
        file_put_contents($this->tempFile, $sourcecode);

        $analyzer = new Analyzer($this->output);
        $catalog = $analyzer->analyze([$this->tempFile]);
        $model = $catalog->entries;

        $this->assertCount(2, $model);
        $this->assertArrayHasKey('ExceptionReason:ERR_A', $model);
        $this->assertArrayHasKey('ExceptionReason:ERR_B', $model);

        $this->assertEquals('Biz A', $model['ExceptionReason:ERR_A']->values['businessReason']);
        $this->assertEquals(['MyClass::methodWithTwoAttributes:6'], $model['ExceptionReason:ERR_A']->thrown_from);

        $this->assertEquals('Biz B', $model['ExceptionReason:ERR_B']->values['businessReason']);
        $this->assertEquals(['MyClass::methodWithTwoAttributes:6'], $model['ExceptionReason:ERR_B']->thrown_from);
    }

    public function testAnalyzeWithDuplicateAttributeIdentifiersOnSameMethod(): void
    {
        $sourcecode = <<<'PHP'
            <?php
            class MyClass {
                #[ExceptionReason(identifier: 'DUP', technicalReason: 'Tech 1', businessReason: 'Biz 1')]
                #[ExceptionReason(identifier: 'DUP', technicalReason: 'Tech 2', businessReason: 'Biz 2')]
                public function methodWithDuplicateIdentifiers() {
                    throw MyException::error();
                }
            }
            PHP;
        file_put_contents($this->tempFile, $sourcecode);

        $analyzer = new Analyzer($this->output);
        $catalog = $analyzer->analyze([$this->tempFile]);
        $model = $catalog->entries;

        // It should now have two entries because reasons are different
        $this->assertCount(2, $model);
        $this->assertArrayHasKey('ExceptionReason:DUP', $model);
        $this->assertArrayHasKey('ExceptionReason:DUP_1', $model);
        $this->assertEquals('Biz 1', $model['ExceptionReason:DUP']->values['businessReason']);
        $this->assertEquals('Biz 2', $model['ExceptionReason:DUP_1']->values['businessReason']);
        $this->assertEquals(['MyClass::methodWithDuplicateIdentifiers:6'], $model['ExceptionReason:DUP']->thrown_from);
    }

    public function testAnalyzeTriggersWarningForDuplicateIdentifiersWithDifferentReasons(): void
    {
        $sourcecode = <<<'PHP'
            <?php
            class MyClass {
                #[ExceptionReason(identifier: 'DUP', technicalReason: 'Tech 1', businessReason: 'Biz 1')]
                #[ExceptionReason(identifier: 'DUP', technicalReason: 'Tech 2', businessReason: 'Biz 2')]
                public function method1() { throw E::e(); }
            }
            PHP;
        file_put_contents($this->tempFile, $sourcecode);

        $analyzer = new Analyzer($this->output);
        $analyzer->analyze([$this->tempFile]);

        $issues = $analyzer->getValidationIssues();
        $warnings = array_filter($issues, fn ($i) => ValidationIssue::SEVERITY_WARNING === $i->severity);
        $this->assertCount(1, $warnings);
        $warning = reset($warnings);
        $this->assertInstanceOf(ValidationIssue::class, $warning);
        $this->assertStringContainsString("Duplicate identifier 'DUP' found with different reasons", $warning->message);
    }

    public function testAnalyzeWithSameIdentifierOnDifferentMethods(): void
    {
        $sourcecode = <<<'PHP'
            <?php
            class ClassA {
                #[ExceptionReason(identifier: 'SHARED', technicalReason: 'Tech', businessReason: 'Biz')]
                public function methodA() { throw E::e(); }
            }
            class ClassB {
                #[ExceptionReason(identifier: 'SHARED', technicalReason: 'Tech', businessReason: 'Biz')]
                public function methodB() { throw E::e(); }
            }
            PHP;
        file_put_contents($this->tempFile, $sourcecode);

        $analyzer = new Analyzer($this->output);
        $catalog = $analyzer->analyze([$this->tempFile]);
        $model = $catalog->entries;

        $this->assertCount(1, $model);
        $this->assertArrayHasKey('ExceptionReason:SHARED', $model);
        $this->assertCount(2, $model['ExceptionReason:SHARED']->thrown_from);
        $this->assertContains('ClassA::methodA:4', $model['ExceptionReason:SHARED']->thrown_from);
        $this->assertContains('ClassB::methodB:8', $model['ExceptionReason:SHARED']->thrown_from);
    }

    public function testAnalyzeWithMultipleAttributesAndMultipleThrows(): void
    {
        $sourcecode = <<<'PHP'
            <?php
            class MyClass {
                #[ExceptionReason(identifier: 'E1', technicalReason: 'T1', businessReason: 'B1')]
                #[ExceptionReason(identifier: 'E2', technicalReason: 'T2', businessReason: 'B2')]
                public function multipleBoth() {
                    if (rand(0,1)) {
                         throw Exc1::error();
                    }
                    throw Exc2::error();
                }
            }
            PHP;
        file_put_contents($this->tempFile, $sourcecode);

        $analyzer = new Analyzer($this->output);
        $catalog = $analyzer->analyze([$this->tempFile]);
        $model = $catalog->entries;

        $this->assertCount(2, $model);
        // It should now list all exceptions found in the method for all its attributes
        $this->assertEquals('Exc1::error, Exc2::error', $model['ExceptionReason:E1']->exception);
        $this->assertEquals('Exc1::error, Exc2::error', $model['ExceptionReason:E2']->exception);
    }

    public function testAnalyzeSuppressesWarningForDuplicateIdentifiers(): void
    {
        $sourcecode = <<<'PHP'
            <?php
            class MyClass {
                #[ExceptionReason(identifier: 'DUP', technicalReason: 'Tech 1', businessReason: 'Biz 1')]
                #[ExceptionReason(identifier: 'DUP', technicalReason: 'Tech 2', businessReason: 'Biz 2')]
                public function method1() { throw E::e(); }
            }
            PHP;
        file_put_contents($this->tempFile, $sourcecode);

        $analyzer = new Analyzer($this->output, new AnalyzerConfig(suppressDuplicateIdentifierWarning: true));
        $analyzer->analyze([$this->tempFile]);

        $issues = $analyzer->getValidationIssues();
        $warnings = array_filter($issues, fn ($i) => ValidationIssue::SEVERITY_WARNING === $i->severity);
        $this->assertCount(0, $warnings);
    }
}
