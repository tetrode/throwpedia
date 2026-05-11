<?php

declare(strict_types=1);

namespace App\Service;

use Tetrode\Throwpedia\Attributes\ExceptionReason;
use App\Exception\PaymentException;
use InvalidArgumentException;

class PaymentService
{
    #[ExceptionReason(code: 'INVALID_AMOUNT', technicalReason: 'Amount is <= 0', businessReason: 'Please enter a valid amount.')]
    #[ExceptionReason(code: 'PAYMENT_FAILED', technicalReason: 'Gateway timeout', businessReason: 'We could not process your payment at this time.')]
    public function pay(int $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be positive');
        }

        // ... logic
        throw PaymentException::processingError('Insufficient funds');
    }
}
