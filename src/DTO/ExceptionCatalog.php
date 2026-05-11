<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia\DTO;

readonly class ExceptionCatalog
{
    /**
     * @param array<string, ExceptionModelEntry> $entries
     */
    public function __construct(
        public array $entries,
    ) {
    }
}
