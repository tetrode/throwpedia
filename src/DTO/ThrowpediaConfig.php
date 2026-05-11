<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia\DTO;

readonly class ThrowpediaConfig
{
    /**
     * @param string[] $sources
     * @param string[] $attributes
     * @param AttributeField[] $fields
     * @param OutputTarget[] $outputs
     */
    public function __construct(
        public array $sources,
        public array $attributes,
        public array $fields,
        public array $outputs,
        public bool $allowDirectNew,
        public string $projectRoot = '',
    ) {
    }
}
