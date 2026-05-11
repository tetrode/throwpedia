<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia\Tests;

use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use Tetrode\Throwpedia\Extractor;
use Tetrode\Throwpedia\IO\NullOutput;

class ExtractorTest extends TestCase
{
    private Extractor $extractor;
    private $parser;

    protected function setUp(): void
    {
        $this->extractor = new Extractor(new NullOutput());
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    private function analyzeCode(string $code): array
    {
        $stmts = $this->parser->parse($code);
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
        $code = <<<'PHP'
<?php
namespace App;
class MyClass {
    #[ExceptionReason(code: 'ERR_001', technical: 'Tech', business: 'Biz')]
    public function doSomething() {
        throw MyException::invalidInput();
    }
}
PHP;
        $this->extractor->setCurrentFile('test.php');
        $results = $this->analyzeCode($code);

        $this->assertCount(1, $results);
        $key = array_key_first($results);
        $data = $results[$key];

        $this->assertEquals('App\MyClass', $data['class']);
        $this->assertEquals('doSomething', $data['method']);
        $this->assertCount(1, $data['attributes']);
        $this->assertEquals('ERR_001', $data['attributes'][0]['code']);
        $this->assertEquals('Tech', $data['attributes'][0]['technical']);
        $this->assertEquals('Biz', $data['attributes'][0]['business']);
        $this->assertEquals(['App\MyException::invalidInput'], $data['throws']);
    }

    public function testExtractsDirectNewThrows(): void
    {
        $code = <<<'PHP'
<?php
class MyClass {
    public function doSomething() {
        throw new \Exception('Oops');
    }
}
PHP;
        $this->extractor->setCurrentFile('test.php');
        $this->analyzeCode($code);

        $directNew = $this->extractor->getDirectNewThrows();
        $this->assertCount(1, $directNew);
        $this->assertEquals('Exception', $directNew[0]['exception']);
        $this->assertEquals('MyClass', $directNew[0]['class']);
        $this->assertEquals('doSomething', $directNew[0]['method']);
    }

    public function testHandlePositionalArgumentsInAttribute(): void
    {
        $code = <<<'PHP'
<?php
class MyClass {
    #[ExceptionReason('ERR_POS', 'Tech Pos', 'Biz Pos')]
    public function posMethod() {
        throw MyException::error();
    }
}
PHP;
        $this->extractor->setCurrentFile('test.php');
        $results = $this->analyzeCode($code);

        $key = array_key_first($results);
        $attr = $results[$key]['attributes'][0];
        $this->assertEquals('ERR_POS', $attr['code']);
        $this->assertEquals('Tech Pos', $attr['technical']);
        $this->assertEquals('Biz Pos', $attr['business']);
    }
}
