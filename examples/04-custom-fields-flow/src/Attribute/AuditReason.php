<?php

declare(strict_types=1);

namespace App\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class AuditReason
{
    public function __construct(
        public string $identifier,
        public string $action
    ) {
    }
}
