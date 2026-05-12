<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class ExceptionReason
{
    public function __construct(
        public string $identifier = 'UNKNOWN',
        public string $technicalReason = 'technical reason',
        public string $businessReason = 'business reason'
    ) {
    }
}
