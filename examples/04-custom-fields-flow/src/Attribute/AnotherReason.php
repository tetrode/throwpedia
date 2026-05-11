<?php

declare(strict_types=1);

namespace App\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class AnotherReason
{
    public function __construct(
        public string $code,
        public string $note
    ) {
    }
}
