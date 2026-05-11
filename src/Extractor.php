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
use Tetrode\Throwpedia\DTO\AttributeField;
use Tetrode\Throwpedia\DTO\DirectNewThrow;
use Tetrode\Throwpedia\DTO\ExceptionAttribute;
use Tetrode\Throwpedia\DTO\ExtractionResults;
use Tetrode\Throwpedia\DTO\MethodAnalysisResult;
use Tetrode\Throwpedia\DTO\ValidationIssue;
use Tetrode\Throwpedia\IO\OutputInterface;

class Extractor extends NodeVisitorAbstract
{
    private ?string $currentNamespace = null;
    private ?string $currentClass = null;
    private ?ClassMethod $currentMethod = null;
    private ?Function_ $currentFunction = null;
    private ?string $currentMethodKey = null;

    /** @var array<string, MethodAnalysisResult> */
    private array $methods = [];

    /** @var DirectNewThrow[] */
    private array $directNewThrows = [];

    /** @var ValidationIssue[] */
    private array $validationIssues = [];

    private string $currentFile = '';

    /** @var array<string> */
    private array $targetAttributes = ['ExceptionReason'];

    /** @var AttributeField[] */
    private array $fields = [];

    public function __construct(
        /** @phpstan-ignore-next-line this is a stream */
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

    /**
     * @param AttributeField[] $fields
     */
    public function setFields(array $fields): void
    {
        $this->fields = $fields;
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
            $node instanceof Namespace_                      => $this->handleNamespace($node),
            $node instanceof Class_, $node instanceof Trait_ => $this->handleClassLike($node),
            $node instanceof ClassMethod                     => $this->handleClassMethod($node),
            $node instanceof Function_                       => $this->handleFunction($node),
            $node instanceof Node\Expr\Throw_                => $this->handleThrow($node),
            default                                          => null,
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

        if ($node instanceof Class_) {
            $fullClassName = ($this->currentNamespace ? $this->currentNamespace . '\\' : '') . ($this->currentClass ?? '');
            $shortName = $node->name ? $node->name->toString() : '';

            $isAttribute = false;
            foreach ($this->targetAttributes as $target) {
                if ($shortName === $target || $fullClassName === $target) {
                    $isAttribute = true;
                    break;
                }
            }

            if ($isAttribute) {
                $this->validateAttributeClass($node);
            }
        }
    }

    private function validateAttributeClass(Class_ $node): void
    {
        $constructor = $node->getMethod('__construct');
        if (!$constructor || empty($this->fields)) {
            return;
        }

        $params = $constructor->params;
        foreach ($this->fields as $index => $field) {
            $param = $params[$index] ?? null;
            if (!$param) {
                $this->validationIssues[] = new ValidationIssue(
                    message: \sprintf("Attribute class '%s' is missing parameter '%s' at position %d", $node->name->toString(), $field->name, $index),
                    severity: ValidationIssue::SEVERITY_ERROR,
                    file: $this->currentFile,
                    line: $node->getLine()
                );
                continue;
            }

            $paramName = $param->var instanceof Node\Expr\Variable ? (string)$param->var->name : '';
            if ($paramName !== $field->name) {
                $this->validationIssues[] = new ValidationIssue(
                    message: \sprintf("Attribute class '%s' parameter at position %d should be named '%s', found '%s'", $node->name->toString(), $index, $field->name, $paramName),
                    severity: ValidationIssue::SEVERITY_WARNING,
                    file: $this->currentFile,
                    line: $node->getLine()
                );
            }
        }
    }

    private function handleClassMethod(ClassMethod $node): void
    {
        $this->currentMethod = $node;
        $methodName = $node->name->toString();
        $fullClassName = ($this->currentNamespace ? $this->currentNamespace . '\\' : '') . ($this->currentClass ?? '');

        $attributes = $this->extractAttributes($node);

        $this->currentMethodKey = $this->currentFile . ':' . $node->getLine();
        $this->methods[$this->currentMethodKey] = new MethodAnalysisResult(
            file: $this->currentFile,
            line: $node->getLine(),
            class: $fullClassName,
            method: $methodName,
            attributes: $attributes,
            throws: [],
        );
    }

    private function handleFunction(Function_ $node): void
    {
        $this->currentFunction = $node;
        $functionName = $node->name->toString();
        $fullNamespace = ($this->currentNamespace ? $this->currentNamespace . '\\' : '');

        $attributes = $this->extractAttributes($node);

        $this->currentMethodKey = $this->currentFile . ':' . $node->getLine();
        $this->methods[$this->currentMethodKey] = new MethodAnalysisResult(
            file: $this->currentFile,
            line: $node->getLine(),
            class: $fullNamespace,
            method: $functionName,
            attributes: $attributes,
            throws: [],
        );
    }

    /**
     * @return ExceptionAttribute[]
     */
    private function extractAttributes(Node\FunctionLike $node): array
    {
        /** @var ExceptionAttribute[] $attributes */
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
                    /** @var array<int|string, string> $args */
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

                    if (!empty($this->fields)) {
                        $values = [];
                        foreach ($this->fields as $index => $field) {
                            $values[$field->name] = (string)($args[$field->name] ?? $args[$index] ?? '');
                        }
                        $attributes[] = new ExceptionAttribute($values);
                    } else {
                        if (isset($args[0]) || isset($args['code'])) {
                            $attributes[] = new ExceptionAttribute([
                                'code' => (string)($args['code'] ?? $args[0] ?? 'UNKNOWN'),
                                'technicalReason' => (string)($args['technicalReason'] ?? $args['technical'] ?? $args[1] ?? ''),
                                'businessReason' => (string)($args['businessReason'] ?? $args['business'] ?? $args[2] ?? ''),
                            ]);
                        }
                    }
                }
            }
        }

        return $attributes;
    }

    private function handleThrow(Node $node): void
    {
        /** @var Node\Expr\Throw_ $node */
        if ($node->expr instanceof StaticCall) {
            $class = $node->expr->class;
            $method = $node->expr->name;

            if ($class instanceof Name && $method instanceof Node\Identifier) {
                $className = $class->toString();
                $methodName = $method->toString();

                if ($this->currentMethodKey && isset($this->methods[$this->currentMethodKey])) {
                    $method = $this->methods[$this->currentMethodKey];
                    $method->throws[] = $className . '::' . $methodName;
                }
            }
        } elseif ($node->expr instanceof New_) {
            $class = $node->expr->class;
            if ($class instanceof Name) {
                $className = $class->toString();
                if ($this->currentMethodKey && isset($this->methods[$this->currentMethodKey])) {
                    $method = $this->methods[$this->currentMethodKey];
                    $method->throws[] = $className;
                }

                $this->directNewThrows[] = new DirectNewThrow(
                    file: $this->currentFile,
                    line: $node->getLine(),
                    class: ($this->currentNamespace ? $this->currentNamespace . '\\' : '') . ($this->currentClass ?? ''),
                    method: ($this->currentMethod ? $this->currentMethod->name->toString() : ($this->currentFunction ? $this->currentFunction->name->toString() : 'unknown')),
                    exception: $className,
                );
            }
        }
    }

    public function leaveNode(Node $node): int|Node|array|null
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
        return null;
    }

    public function getExtractionResults(): ExtractionResults
    {
        return new ExtractionResults($this->methods, $this->directNewThrows, $this->validationIssues);
    }

    /**
     * @return array<string, MethodAnalysisResult>
     */
    public function getResults(): array
    {
        return $this->methods;
    }

    /**
     * @return DirectNewThrow[]
     */
    public function getDirectNewThrows(): array
    {
        return $this->directNewThrows;
    }
}
