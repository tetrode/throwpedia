<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia\DTO;

readonly class ExceptionModelEntry
{
    /**
     * @param string[] $thrown_from
     */
    public function __construct(
        public string $business,
        public string $technical,
        public string $exception,
        public array $thrown_from,
        public ?string $code = null,
    ) {
    }
}
