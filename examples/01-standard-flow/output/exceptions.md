# Business Exceptions Catalog

**Project:** tetrode/throwpedia (^8.5)
**Exceptions found:** 2

**Throwpedia Version:** 0.1.0
**Scan time:** 2026-05-11 20:57:42

## ExceptionReason

| Code | Technical Reason | Business Reason | Exception | Thrown From |
| ---- | ---------------- | --------------- | --------- | ----------- |
| USER_ALREADY_EXISTS | Duplicate entry for unique email field. | An account with this email already exists. | `App\Exception\UserException::emailTaken` | App\Service\UserService::register |
| USER_NOT_FOUND | The requested user ID does not exist in the database. | The user you are looking for could not be found. | `App\Exception\UserException::notFound` | App\Service\UserService::getUser |
