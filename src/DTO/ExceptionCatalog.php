<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia\DTO;

readonly class ExceptionCatalog
{
    /**
     * @param array<string, ExceptionModelEntry> $entries
     * @param array{name: string, php: string, total_exceptions: int} $project
     * @param array{version: string, scan_time: string} $meta
     */
    public function __construct(
        public array $entries,
        public array $project = ['name' => 'unknown', 'php' => 'unknown', 'total_exceptions' => 0],
        public array $meta = ['version' => '0.0.0', 'scan_time' => 'unknown'],
    ) {
    }
}
