<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia\DTO;

readonly class ExceptionModelEntry
{
    /**
     * @param array<string, string> $values
     * @param string[] $thrown_from
     */
    public function __construct(
        public array $values,
        public string $exception,
        public array $thrown_from,
    ) {
    }
}
