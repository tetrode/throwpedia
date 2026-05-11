<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia\DTO;

readonly class DirectNewThrow
{
    public function __construct(
        public string $file,
        public int $line,
        public string $class,
        public string $method,
        public string $exception,
    ) {
    }
}
