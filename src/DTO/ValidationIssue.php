<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia\DTO;

readonly class ValidationIssue
{
    public const SEVERITY_ERROR = 'error';
    public const SEVERITY_WARNING = 'warning';

    public function __construct(
        public string $message,
        public string $severity,
        public ?string $file = null,
        public ?int $line = null,
        public ?string $class = null,
        public ?string $method = null,
    ) {
    }
}
