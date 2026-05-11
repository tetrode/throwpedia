<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia\DTO;

readonly class ExtractionResults
{
    /**
     * @param array<string, MethodAnalysisResult> $methods
     * @param DirectNewThrow[] $directNewThrows
     * @param ValidationIssue[] $validationIssues
     */
    public function __construct(
        public array $methods,
        public array $directNewThrows,
        public array $validationIssues = [],
    ) {
    }
}
