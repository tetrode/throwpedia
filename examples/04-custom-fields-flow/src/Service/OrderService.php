<?php

declare(strict_types=1);

namespace App\Service;

use App\Attribute\AnotherReason;
use App\Attribute\AuditReason;
use App\Attribute\CustomReason;
use App\Exception\OrderException;

class OrderService
{
    #[CustomReason(
        identifier: 'OUT_OF_STOCK',
        severity: 'critical',
        ticket: 'JIRA-123'
    )]
    #[AnotherReason(
        identifier: 'LOGISTIC_DELAY',
        note: 'Expected delay 2 days'
    )]
    public function checkout(string $sku): void
    {
        // ... logic
        throw OrderException::insufficientStock($sku);
    }

    #[CustomReason(
        identifier: 'INVALID_COUPON',
        severity: 'low',
        ticket: 'JIRA-456'
    )]
    #[AuditReason(
        identifier: 'SECURITY_ALERT',
        action: 'BLOCK_USER'
    )]
    public function applyCoupon(string $coupon): void
    {
        // ... logic
        throw OrderException::invalidCoupon($coupon);
    }
}
