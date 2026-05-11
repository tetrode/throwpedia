<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia\DTO;

readonly class ScanMeta
{
    public function __construct(
        public string $version = '0.0.0',
        public string $scan_time = 'unknown',
    ) {
    }
}
