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

class Extractor extends NodeVisitorAbstract
{
    private ?string $currentNamespace = null;
    private ?string $currentClass = null;
    private ?ClassMethod $currentMethod = null;
    private ?Function_ $currentFunction = null;
    private ?string $currentMethodKey = null;

    /** @var array<string, array{file: string, line: int, class: string, method: string, attributes: array<mixed>, throws: array<string>}> */
    private array $methods = [];

    /** @var array<array{file: string, line: int, class: string, method: string, exception: string, factory: string}> */
    private array $directNewThrows = [];

    private string $currentFile = '';

    /** @var array<string> */
    private array $targetAttributes = ['ExceptionReason'];

    private int $verbosity = 0;

    public function setTargetAttributes(array $attributes): void
    {
        $this->targetAttributes = $attributes;
    }

    public function setVerbosity(int $level): void
    {
        $this->verbosity = $level;
    }

    public function setCurrentFile(string $file): void
    {
        $this->currentFile = $file;
        if ($this->verbosity >= 1) {
            echo "Analyzing file: $file\n";
        }
    }

    public function enterNode(Node $node): null
    {
        if ($node instanceof Namespace_) {
            $this->handleNamespace($node);
        }

        if ($node instanceof Class_ || $node instanceof Trait_) {
            $this->handleClassLike($node);
        }

        if ($node instanceof ClassMethod) {
            $this->handleClassMethod($node);
            if ($this->verbosity >= 2) {
                $methodName = $node->name->toString();
                $fullClassName = ($this->currentNamespace ? $this->currentNamespace . '\\' : '') . $this->currentClass;
                echo "  Analyzing method: $fullClassName::$methodName\n";
            }
        }

        if ($node instanceof Function_) {
            $this->handleFunction($node);
            if ($this->verbosity >= 2) {
                $functionName = $node->name->toString();
                $fullNamespace = $this->currentNamespace ? $this->currentNamespace . '\\' : '';
                echo "  Analyzing function: $fullNamespace$functionName\n";
            }
        }

        if ($node instanceof Node\Stmt\Throw_ || $node instanceof Node\Expr\Throw_) {
            $this->handleThrow($node);
        }

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
        $fullClassName = ($this->currentNamespace ? $this->currentNamespace . '\\' : '') . $this->currentClass;

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
                            $args[] = $arg->value->value;
                        } elseif (null !== $arg->name) {
                            if ($arg->value instanceof Node\Scalar\String_) {
                                $args[$arg->name->toString()] = $arg->value->value;
                            }
                        }
                    }

                    if (isset($args[0]) || isset($args['code'])) {
                        if (isset($args[0])) {
                            $attributes[] = [
                                'code'      => $args[0] ?? 'UNKNOWN',
                                'technical' => $args[1] ?? '',
                                'business'  => $args[2] ?? '',
                            ];
                        } else {
                            $attributes[] = [
                                'code'      => $args['code'] ?? 'UNKNOWN',
                                'technical' => $args['technicalReason'] ?? $args['technical'] ?? '',
                                'business'  => $args['businessReason'] ?? $args['business'] ?? '',
                            ];
                        }
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
                $locationName = 'unknown';
                if ($this->currentMethod) {
                    $locationName = ($this->currentNamespace ? $this->currentNamespace . '\\' : '') . $this->currentClass . '::' . $this->currentMethod->name->toString();
                } elseif ($this->currentFunction) {
                    $locationName = ($this->currentNamespace ? $this->currentNamespace . '\\' : '') . $this->currentFunction->name->toString();
                }

                $this->directNewThrows[] = [
                    'file'      => $this->currentFile,
                    'line'      => $node->getLine(),
                    'class'     => ($this->currentNamespace ? $this->currentNamespace . '\\' : '') . ($this->currentClass ?? ''),
                    'method'    => ($this->currentMethod ? $this->currentMethod->name->toString() : ($this->currentFunction ? $this->currentFunction->name->toString() : 'unknown')),
                    'exception' => $class->toString(),
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
