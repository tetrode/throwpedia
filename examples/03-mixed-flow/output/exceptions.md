# Business Exceptions Catalog

**Project:** unknown (unknown)
**Exceptions found:** 5

**Throwpedia Version:** 0.2.0
**Scan time:** 2026-05-12 14:22:48

## ExceptionReason

| Code | Technical Reason | Business Reason | Exception | Thrown From |
| ---- | ---------------- | --------------- | --------- | ----------- |
| DIRECT_NEW_INVALIDARGUMENTEXCEPTION | Thrown from App\Service\PaymentService::payNow:18 | Direct instantiation of InvalidArgumentException | `InvalidArgumentException` | App\Service\PaymentService::payNow:18 |
| DIRECT_NEW_INVALIDARGUMENTEXCEPTION | Thrown from App\Service\PaymentService::payLater:30 | Direct instantiation of InvalidArgumentException | `InvalidArgumentException` | App\Service\PaymentService::payLater:30 |
| DIRECT_NEW_INVALIDARGUMENTEXCEPTION | Thrown from App\Service\PaymentRetryService::retry:18 | Direct instantiation of InvalidArgumentException | `InvalidArgumentException` | App\Service\PaymentRetryService::retry:18 |
| INVALID_AMOUNT | Amount is <= 0 | Please enter a valid amount. | `InvalidArgumentException, App\Exception\PaymentException::processingError` | App\Service\PaymentService::payNow:18 <br>App\Service\PaymentService::payNow:22 <br>App\Service\PaymentService::payLater:30 <br>App\Service\PaymentService::payLater:34 <br>App\Service\PaymentRetryService::retry:18 <br>App\Service\PaymentRetryService::retry:22 |
| PAYMENT_FAILED | Gateway timeout | We could not process your payment at this time. | `InvalidArgumentException, App\Exception\PaymentException::processingError` | App\Service\PaymentService::payNow:18 <br>App\Service\PaymentService::payNow:22 <br>App\Service\PaymentService::payLater:30 <br>App\Service\PaymentService::payLater:34 <br>App\Service\PaymentRetryService::retry:18 <br>App\Service\PaymentRetryService::retry:22 |
