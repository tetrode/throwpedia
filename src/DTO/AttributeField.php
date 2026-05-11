<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia\DTO;

readonly class AttributeField
{
    public function __construct(
        public string $name,
        public string $label,
        public bool $isCode = false,
    ) {
    }
}
