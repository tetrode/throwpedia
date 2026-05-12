<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia\DTO;

class MethodAnalysisResult
{
    /**
     * @param ExceptionAttribute[] $attributes
     * @param array<int, array{exception: string, line: int}> $throws
     */
    public function __construct(
        public readonly string $file,
        public readonly int $line,
        public readonly string $class,
        public readonly string $method,
        public readonly array $attributes,
        public array $throws,
    ) {
    }
}
