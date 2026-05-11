# Business Exceptions Catalog

| Code | Technical Reason | Business Reason | Exception | Thrown From |
| ---- | ---------------- | --------------- | --------- | ----------- |
| DIRECT_NEW_INVALIDARGUMENTEXCEPTION | Thrown from App\Service\PaymentService::pay | Direct instantiation of InvalidArgumentException | `InvalidArgumentException` | App\Service\PaymentService::pay |
| INVALID_AMOUNT | Amount is <= 0 | Please enter a valid amount. | `InvalidArgumentException, App\Exception\PaymentException::processingError` | App\Service\PaymentService::pay |
| PAYMENT_FAILED | Gateway timeout | We could not process your payment at this time. | `InvalidArgumentException, App\Exception\PaymentException::processingError` | App\Service\PaymentService::pay |
