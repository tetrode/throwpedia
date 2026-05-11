<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia\DTO;

readonly class OutputTarget
{
    public function __construct(
        public string $path,
        public string $extension,
    ) {
    }
}
