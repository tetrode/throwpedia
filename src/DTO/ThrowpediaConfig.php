<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia\DTO;

readonly class ThrowpediaConfig
{
    /**
     * @param string[] $sources
     * @param array<string, AttributeField[]> $attributeFields
     * @param OutputTarget[] $outputs
     */
    public function __construct(
        public array $sources,
        public array $attributeFields,
        public array $outputs,
        public bool $allowDirectNew,
        public bool $suppressDuplicateCodeWarning = false,
        public string $projectRoot = '',
    ) {
    }
}
