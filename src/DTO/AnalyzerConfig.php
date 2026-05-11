<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia\DTO;

readonly class AnalyzerConfig
{
    /**
     * @param string[] $attributes
     */
    public function __construct(
        public array $attributes = ['ExceptionReason'],
        public bool $allowDirectNew = false,
        public string $projectRoot = '',
    ) {
    }
}
