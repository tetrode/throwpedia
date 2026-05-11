# Business Exceptions Catalog

## AnotherReason

| Quick Code | Simple Note | Exception | Thrown From |
| ---------- | ----------- | --------- | ----------- |
| LOGISTIC_DELAY | Expected delay 2 days | `App\Exception\OrderException::insufficientStock` | App\Service\OrderService::checkout |

## AuditReason

| Audit Code | Audit Action | Exception | Thrown From |
| ---------- | ------------ | --------- | ----------- |
| SECURITY_ALERT | BLOCK_USER | `App\Exception\OrderException::invalidCoupon` | App\Service\OrderService::applyCoupon |

## CustomReason

| Error Code | Impact Level | Tracker ID | Exception | Thrown From |
| ---------- | ------------ | ---------- | --------- | ----------- |
| INVALID_COUPON | low | JIRA-456 | `App\Exception\OrderException::invalidCoupon` | App\Service\OrderService::applyCoupon |
| OUT_OF_STOCK | critical | JIRA-123 | `App\Exception\OrderException::insufficientStock` | App\Service\OrderService::checkout |
