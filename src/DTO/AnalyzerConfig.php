<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia\DTO;

readonly class AnalyzerConfig
{
    /**
     * @param string[] $attributes
     * @param AttributeField[] $fields
     */
    public function __construct(
        public array $attributes = ['ExceptionReason'],
        public array $fields = [],
        public bool $allowDirectNew = false,
        public string $projectRoot = '',
    ) {
    }
}
