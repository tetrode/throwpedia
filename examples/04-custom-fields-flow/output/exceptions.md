# Business Exceptions Catalog

| Error Code | Impact Level | Tracker ID | Exception | Thrown From |
| ---------- | ------------ | ---------- | --------- | ----------- |
| INVALID_COUPON | low | JIRA-456 | `App\Exception\OrderException::invalidCoupon` | App\Service\OrderService::applyCoupon |
| LOGISTIC_DELAY | medium | JIRA-999 | `App\Exception\OrderException::insufficientStock` | App\Service\OrderService::checkout |
| OUT_OF_STOCK | critical | JIRA-123 | `App\Exception\OrderException::insufficientStock` | App\Service\OrderService::checkout |
| SECURITY_ALERT | high | SEC-789 | `App\Exception\OrderException::invalidCoupon` | App\Service\OrderService::applyCoupon |
