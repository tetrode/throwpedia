<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia\DTO;

readonly class ExceptionAttribute
{
    public function __construct(
        public string $code,
        public string $technical,
        public string $business,
    ) {
    }
}
