# Business Exceptions Catalog

**Project:** unknown (unknown)
**Exceptions found:** 4

**Throwpedia Version:** 0.2.0
**Scan time:** 2026-05-13 11:18:26

## AnotherReason

| Quick Code | Simple Note | Exception | Thrown From |
| ---------- | ----------- | --------- | ----------- |
| LOGISTIC_DELAY | Expected delay 2 days | `App\Exception\OrderException::insufficientStock` | App\Service\OrderService::checkout:26 |

### Calling Trees

#### LOGISTIC_DELAY

```
App\Service\OrderService::checkout()
     throws App\Exception\OrderException::insufficientStock

```

## AuditReason

| Audit Code | Audit Action | Exception | Thrown From |
| ---------- | ------------ | --------- | ----------- |
| SECURITY_ALERT | BLOCK_USER | `App\Exception\OrderException::invalidCoupon` | App\Service\OrderService::applyCoupon:41 |

### Calling Trees

#### SECURITY_ALERT

```
App\Service\OrderService::applyCoupon()
     throws App\Exception\OrderException::invalidCoupon

```

## CustomReason

| Error Code | Impact Level | Tracker ID | Exception | Thrown From |
| ---------- | ------------ | ---------- | --------- | ----------- |
| INVALID_COUPON | low | JIRA-456 | `App\Exception\OrderException::invalidCoupon` | App\Service\OrderService::applyCoupon:41 |
| OUT_OF_STOCK | critical | JIRA-123 | `App\Exception\OrderException::insufficientStock` | App\Service\OrderService::checkout:26 |

### Calling Trees

#### INVALID_COUPON

```
App\Service\OrderService::applyCoupon()
     throws App\Exception\OrderException::invalidCoupon

```

#### OUT_OF_STOCK

```
App\Service\OrderService::checkout()
     throws App\Exception\OrderException::insufficientStock

```
