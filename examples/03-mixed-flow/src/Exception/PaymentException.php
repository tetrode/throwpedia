<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

class PaymentException extends RuntimeException
{
    public static function processingError(string $reason): self
    {
        return new self($reason);
    }
}
