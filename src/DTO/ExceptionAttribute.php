<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia\DTO;

readonly class ExceptionAttribute
{
    /**
     * @param array<string, string> $values
     */
    public function __construct(
        public string $attributeName,
        public array $values,
    ) {
    }
}
