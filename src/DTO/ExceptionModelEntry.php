<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia\DTO;

readonly class ExceptionModelEntry
{
    /**
     * @param array<string, string> $values
     * @param string[] $thrown_from
     * @param array<int, array<string, mixed>> $call_trees
     */
    public function __construct(
        public string $attributeName,
        public array $values,
        public string $exception,
        public array $thrown_from,
        public array $call_trees = [],
    ) {
    }
}
