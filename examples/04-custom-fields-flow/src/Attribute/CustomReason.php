<?php

declare(strict_types=1);

namespace App\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class CustomReason
{
    public function __construct(
        public string $code,
        public string $severity,
        public string $ticket
    ) {
    }
}
