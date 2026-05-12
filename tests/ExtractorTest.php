<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia\Tests;

use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use Tetrode\Throwpedia\DTO\AttributeField;
use Tetrode\Throwpedia\DTO\MethodAnalysisResult;
use Tetrode\Throwpedia\Extractor;
use Tetrode\Throwpedia\IO\NullOutput;

class ExtractorTest extends TestCase
{
    private Extractor $extractor;
    private Parser $parser;

    protected function setUp(): void
    {
        $this->extractor = new Extractor(new NullOutput());
        $this->extractor->setAttributeFields([
            'ExceptionReason' => [
                new AttributeField('identifier', 'Identifier', true),
                new AttributeField('technicalReason', 'Technical Reason'),
                new AttributeField('businessReason', 'Business Reason'),
            ],
        ]);
        $this->parser = new ParserFactory()->createForNewestSupportedVersion();
    }

    /**
     * @return array<string, MethodAnalysisResult>
     */
    private function analyzeCode(string $sourcecode): array
    {
        $stmts = $this->parser->parse($sourcecode);
        $this->assertIsArray($stmts);
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $stmts = $traverser->traverse($stmts);

        $traverser = new NodeTraverser();
        $traverser->addVisitor($this->extractor);
        $traverser->traverse($stmts);
        return $this->extractor->getResults();
    }

    public function testExtractsMethodWithAttributeAndStaticCall(): void
    {
        $sourcecode = <<<'PHP'
            <?php
            namespace App;
            class MyClass {
                #[ExceptionReason(identifier: 'ERR_001', technicalReason: 'Tech', businessReason: 'Biz')]
                public function doSomething() {
                    throw MyException::invalidInput();
                }
            }
            PHP;
        $this->extractor->setCurrentFile('test.php');
        $results = $this->analyzeCode($sourcecode);

        $this->assertCount(1, $results);
        $key = array_key_first($results);
        $this->assertIsString($key);
        $data = $results[$key];

        $this->assertEquals('App\MyClass', $data->class);
        $this->assertEquals('doSomething', $data->method);
        $this->assertCount(1, $data->attributes);
        $this->assertEquals('ERR_001', $data->attributes[0]->values['identifier']);
        $this->assertEquals('Tech', $data->attributes[0]->values['technicalReason']);
        $this->assertEquals('Biz', $data->attributes[0]->values['businessReason']);
        $this->assertEquals([['exception' => 'App\MyException::invalidInput', 'line' => 6]], $data->throws);
    }

    public function testExtractsDirectNewThrows(): void
    {
        $sourcecode = <<<'PHP'
            <?php
            class MyClass {
                public function doSomething() {
                    throw new \Exception('Oops');
                }
            }
            PHP;
        $this->extractor->setCurrentFile('test.php');
        $this->analyzeCode($sourcecode);

        $directNew = $this->extractor->getDirectNewThrows();
        $this->assertCount(1, $directNew);
        $this->assertEquals('Exception', $directNew[0]->exception);
        $this->assertEquals('MyClass', $directNew[0]->class);
        $this->assertEquals('doSomething', $directNew[0]->method);
    }

    public function testHandlePositionalArgumentsInAttribute(): void
    {
        $sourcecode = <<<'PHP'
            <?php
            class MyClass {
                #[ExceptionReason('ERR_POS', 'Tech Pos', 'Biz Pos')]
                public function posMethod() {
                    throw MyException::error();
                }
            }
            PHP;
        $this->extractor->setCurrentFile('test.php');
        $results = $this->analyzeCode($sourcecode);

        $key = array_key_first($results);
        $this->assertIsString($key);
        $attr = $results[$key]->attributes[0];
        $this->assertEquals('ERR_POS', $attr->values['identifier']);
        $this->assertEquals('Tech Pos', $attr->values['technicalReason']);
        $this->assertEquals('Biz Pos', $attr->values['businessReason']);
    }

    public function testHandlesMultipleFilesCorrectly(): void
    {
        $sourcecode1 = <<<'PHP'
            <?php
            namespace App;
            class Class1 {
                #[ExceptionReason(identifier: 'E1', technicalReason: 'T1', businessReason: 'B1')]
                public function m1() { throw E::e(); }
            }
            PHP;
        $sourcecode2 = <<<'PHP'
            <?php
            namespace App;
            class Class2 {
                #[ExceptionReason(identifier: 'E2', technicalReason: 'T2', businessReason: 'B2')]
                public function m2() { throw E::e(); }
            }
            PHP;
        $this->extractor->setCurrentFile('file1.php');
        $this->analyzeCode($sourcecode1);
        $this->extractor->setCurrentFile('file2.php');
        $this->analyzeCode($sourcecode2);

        $results = $this->extractor->getResults();
        $this->assertCount(2, $results);

        $keys = array_keys($results);
        $this->assertStringStartsWith('file1.php:', $keys[0]);
        $this->assertStringStartsWith('file2.php:', $keys[1]);

        $this->assertEquals('App\Class1', $results[$keys[0]]->class);
        $this->assertEquals('App\Class2', $results[$keys[1]]->class);
    }

    public function testExtractsMethodWithMultipleAttributes(): void
    {
        $sourcecode = <<<'PHP'
            <?php
            class MyClass {
                #[ExceptionReason(identifier: 'E1', technicalReason: 'T1', businessReason: 'B1')]
                #[ExceptionReason(identifier: 'E2', technicalReason: 'T2', businessReason: 'B2')]
                public function multiAttrMethod() {
                    throw MyException::error();
                }
            }
            PHP;
        $this->extractor->setCurrentFile('test.php');
        $results = $this->analyzeCode($sourcecode);

        $this->assertCount(1, $results);
        $key = array_key_first($results);
        $this->assertIsString($key);
        $attrs = $results[$key]->attributes;
        $this->assertCount(2, $attrs);
        $this->assertEquals('E1', $attrs[0]->values['identifier']);
        $this->assertEquals('E2', $attrs[1]->values['identifier']);
    }

    public function testExtractsFromTrait(): void
    {
        $sourcecode = <<<'PHP'
            <?php
            namespace App;
            trait MyTrait {
                #[ExceptionReason(identifier: 'TRAIT_ERR', technicalReason: 'T', businessReason: 'B')]
                public function traitMethod() {
                    throw MyEx::fail();
                }
            }
            PHP;
        $this->extractor->setCurrentFile('trait.php');
        $results = $this->analyzeCode($sourcecode);
        $this->assertCount(1, $results);
        $data = reset($results);
        $this->assertInstanceOf(MethodAnalysisResult::class, $data);
        $this->assertEquals('App\MyTrait', $data->class);
        $this->assertEquals('traitMethod', $data->method);
    }

    public function testExtractsFromGlobalFunction(): void
    {
        $sourcecode = <<<'PHP'
            <?php
            #[ExceptionReason(identifier: 'FUNC_ERR', technicalReason: 'T', businessReason: 'B')]
            function myGlobalFunction() {
                throw MyEx::fail();
            }
            PHP;
        $this->extractor->setCurrentFile('func.php');
        $results = $this->analyzeCode($sourcecode);
        $this->assertCount(1, $results);
        $data = reset($results);
        $this->assertInstanceOf(MethodAnalysisResult::class, $data);
        $this->assertEquals('', $data->class);
        $this->assertEquals('myGlobalFunction', $data->method);
    }

    public function testExtractsFromExpressionThrow(): void
    {
        $sourcecode = <<<'PHP'
            <?php
            class MyClass {
                public function test($x) {
                    $y = $x ?? throw new \RuntimeException('Fail');
                }
            }
            PHP;
        $this->extractor->setCurrentFile('expr.php');
        $this->analyzeCode($sourcecode);
        $directNew = $this->extractor->getDirectNewThrows();
        $this->assertCount(1, $directNew);
        $this->assertEquals('RuntimeException', $directNew[0]->exception);
    }
}
