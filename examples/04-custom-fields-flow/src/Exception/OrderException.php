<?php

declare(strict_types=1);

namespace App\Exception;

use DomainException;

class OrderException extends DomainException
{
    public static function insufficientStock(string $sku): self
    {
        return new self("Insufficient stock for SKU: $sku");
    }

    public static function invalidCoupon(string $coupon): self
    {
        return new self("Invalid coupon code: $coupon");
    }
}
