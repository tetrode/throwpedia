# Business Exceptions Catalog

**Project:** unknown (unknown)
**Exceptions found:** 4

**Throwpedia Version:** 0.2.0
**Scan time:** 2026-05-12 14:22:48

## AnotherReason

| Quick Code | Simple Note | Exception | Thrown From |
| ---------- | ----------- | --------- | ----------- |
| LOGISTIC_DELAY | Expected delay 2 days | `App\Exception\OrderException::insufficientStock` | App\Service\OrderService::checkout:26 |

## AuditReason

| Audit Code | Audit Action | Exception | Thrown From |
| ---------- | ------------ | --------- | ----------- |
| SECURITY_ALERT | BLOCK_USER | `App\Exception\OrderException::invalidCoupon` | App\Service\OrderService::applyCoupon:41 |

## CustomReason

| Error Code | Impact Level | Tracker ID | Exception | Thrown From |
| ---------- | ------------ | ---------- | --------- | ----------- |
| INVALID_COUPON | low | JIRA-456 | `App\Exception\OrderException::invalidCoupon` | App\Service\OrderService::applyCoupon:41 |
| OUT_OF_STOCK | critical | JIRA-123 | `App\Exception\OrderException::insufficientStock` | App\Service\OrderService::checkout:26 |
