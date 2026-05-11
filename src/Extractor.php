<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeVisitorAbstract;
use Tetrode\Throwpedia\IO\OutputInterface;

class Extractor extends NodeVisitorAbstract
{
    private ?string $currentNamespace = null;
    private ?string $currentClass = null;
    private ?ClassMethod $currentMethod = null;
    private ?Function_ $currentFunction = null;
    private ?string $currentMethodKey = null;

    /** @var array<string, array{file: string, line: int, class: string, method: string, attributes: array<mixed>, throws: array<string>}> */
    private array $methods = [];

    /** @var array<array{file: string, line: int, class: string, method: string, exception: string}> */
    private array $directNewThrows = [];

    private string $currentFile = '';

    /** @var array<string> */
    private array $targetAttributes = ['ExceptionReason'];

    public function __construct(
        private readonly OutputInterface $output,
    ) {
    }

    /**
     * @param array<string> $attributes
     */
    public function setTargetAttributes(array $attributes): void
    {
        $this->targetAttributes = $attributes;
    }


    public function setCurrentFile(string $file): void
    {
        $this->currentFile = $file;
        $this->currentNamespace = null;
        $this->currentClass = null;
        $this->currentMethod = null;
        $this->currentFunction = null;
        $this->currentMethodKey = null;
    }

    public function enterNode(Node $node): null
    {
        match (true) {
            $node instanceof Namespace_ => $this->handleNamespace($node),
            $node instanceof Class_, $node instanceof Trait_ => $this->handleClassLike($node),
            $node instanceof ClassMethod => $this->handleClassMethod($node),
            $node instanceof Function_ => $this->handleFunction($node),
            $node instanceof Node\Stmt\Throw_, $node instanceof Node\Expr\Throw_ => $this->handleThrow($node),
            default => null,
        };

        return null;
    }

    private function handleNamespace(Namespace_ $node): void
    {
        $this->currentNamespace = $node->name ? $node->name->toString() : null;
    }

    private function handleClassLike(Node $node): void
    {
        /** @var Class_|Trait_ $node */
        $this->currentClass = $node->name ? $node->name->toString() : null;
    }

    private function handleClassMethod(ClassMethod $node): void
    {
        $this->currentMethod = $node;
        $methodName = $node->name->toString();
        $fullClassName = ($this->currentNamespace ? $this->currentNamespace . '\\' : '') . ($this->currentClass ?? '');

        $attributes = $this->extractAttributes($node);

        $this->currentMethodKey = $this->currentFile . ':' . $node->getLine();
        $this->methods[$this->currentMethodKey] = [
            'file'       => $this->currentFile,
            'line'       => $node->getLine(),
            'class'      => $fullClassName,
            'method'     => $methodName,
            'attributes' => $attributes,
            'throws'     => [],
        ];
    }

    private function handleFunction(Function_ $node): void
    {
        $this->currentFunction = $node;
        $functionName = $node->name->toString();
        $fullNamespace = ($this->currentNamespace ? $this->currentNamespace . '\\' : '');

        $attributes = $this->extractAttributes($node);

        $this->currentMethodKey = $this->currentFile . ':' . $node->getLine();
        $this->methods[$this->currentMethodKey] = [
            'file'       => $this->currentFile,
            'line'       => $node->getLine(),
            'class'      => $fullNamespace,
            'method'     => $functionName,
            'attributes' => $attributes,
            'throws'     => [],
        ];
    }

    private function extractAttributes(Node\FunctionLike $node): array
    {
        $attributes = [];
        foreach ($node->getAttrGroups() as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $attrName = $attr->name->toString();
                $attrBaseName = $attr->name->getLast();

                $isTarget = false;
                foreach ($this->targetAttributes as $target) {
                    if ($attrName === $target || $attrBaseName === $target) {
                        $isTarget = true;
                        break;
                    }
                }

                if ($isTarget) {
                    $args = [];
                    foreach ($attr->args as $arg) {
                        if ($arg->value instanceof Node\Scalar\String_) {
                            if (null !== $arg->name) {
                                $args[$arg->name->toString()] = $arg->value->value;
                            } else {
                                $args[] = $arg->value->value;
                            }
                        }
                    }

                    if (isset($args[0]) || isset($args['code'])) {
                        $attributes[] = [
                            'code'      => (string)($args['code'] ?? $args[0] ?? 'UNKNOWN'),
                            'technical' => (string)($args['technicalReason'] ?? $args['technical'] ?? $args[1] ?? ''),
                            'business'  => (string)($args['businessReason'] ?? $args['business'] ?? $args[2] ?? ''),
                        ];
                    }
                }
            }
        }

        return $attributes;
    }

    private function handleThrow(Node $node): void
    {
        /** @var Node\Stmt\Throw_|Node\Expr\Throw_ $node */
        if ($node->expr instanceof StaticCall) {
            $class = $node->expr->class;
            $method = $node->expr->name;

            if ($class instanceof Name && $method instanceof Node\Identifier) {
                $className = $class->toString();
                $methodName = $method->toString();

                if ($this->currentMethodKey && isset($this->methods[$this->currentMethodKey])) {
                    $this->methods[$this->currentMethodKey]['throws'][] = $className . '::' . $methodName;
                }
            }
        } elseif ($node->expr instanceof New_) {
            $class = $node->expr->class;
            if ($class instanceof Name) {
                $className = $class->toString();
                if ($this->currentMethodKey && isset($this->methods[$this->currentMethodKey])) {
                    $this->methods[$this->currentMethodKey]['throws'][] = $className;
                }

                $this->directNewThrows[] = [
                    'file'      => $this->currentFile,
                    'line'      => $node->getLine(),
                    'class'     => ($this->currentNamespace ? $this->currentNamespace . '\\' : '') . ($this->currentClass ?? ''),
                    'method'    => ($this->currentMethod ? $this->currentMethod->name->toString() : ($this->currentFunction ? $this->currentFunction->name->toString() : 'unknown')),
                    'exception' => $className,
                ];
            }
        }
    }

    public function leaveNode(Node $node): void
    {
        if ($node instanceof Class_ || $node instanceof Trait_) {
            $this->currentClass = null;
        }
        if ($node instanceof ClassMethod) {
            $this->currentMethod = null;
            $this->currentMethodKey = null;
        }
        if ($node instanceof Function_) {
            $this->currentFunction = null;
            $this->currentMethodKey = null;
        }
    }

    public function getResults(): array
    {
        return $this->methods;
    }

    public function getDirectNewThrows(): array
    {
        return $this->directNewThrows;
    }
}
