<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia\DTO;

readonly class AnalyzerConfig
{
    /**
     * @param array<string, AttributeField[]> $attributeFields
     */
    public function __construct(
        public array $attributeFields = [
            'ExceptionReason' => [
                new AttributeField('code', 'Code', true),
                new AttributeField('technicalReason', 'Technical Reason'),
                new AttributeField('businessReason', 'Business Reason'),
            ],
        ],
        public bool $allowDirectNew = false,
        public bool $suppressDuplicateCodeWarning = false,
        public string $projectRoot = '',
    ) {
    }
}
