<?php

declare(strict_types=1);

namespace App\Service;

use App\Attribute\CustomReason;
use App\Attribute\AnotherReason;
use App\Attribute\AuditReason;
use App\Exception\OrderException;

class OrderService
{
    #[CustomReason(
        code: 'OUT_OF_STOCK',
        severity: 'critical',
        ticket: 'JIRA-123'
    )]
    #[AnotherReason(
        code: 'LOGISTIC_DELAY',
        severity: 'medium',
        ticket: 'JIRA-999'
    )]
    public function checkout(string $sku): void
    {
        // ... logic
        throw OrderException::insufficientStock($sku);
    }

    #[CustomReason(
        code: 'INVALID_COUPON',
        severity: 'low',
        ticket: 'JIRA-456'
    )]
    #[AuditReason(
        code: 'SECURITY_ALERT',
        severity: 'high',
        ticket: 'SEC-789'
    )]
    public function applyCoupon(string $coupon): void
    {
        // ... logic
        throw OrderException::invalidCoupon($coupon);
    }
}
