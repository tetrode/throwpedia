<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia\DTO;

readonly class ProjectInfo
{
    public function __construct(
        public string $name = 'unknown',
        public string $php = 'unknown',
        public int $total_exceptions = 0,
    ) {
    }
}
