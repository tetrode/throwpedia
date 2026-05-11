<?php

declare(strict_types=1);

namespace App\Exception;

use Tetrode\Throwpedia\Attributes\ExceptionReason;
use RuntimeException;

class PaymentException extends RuntimeException
{
    public static function processingError(string $reason): self
    {
        return new self($reason);
    }
}
