# Business Exceptions Catalog

**Project:** unknown (unknown)
**Exceptions found:** 2

**Throwpedia Version:** 0.1.0
**Scan time:** 2026-05-12 13:31:10

## ExReason

| The Reason                                            | Exception                                 | Thrown From                          |
|-------------------------------------------------------|-------------------------------------------|--------------------------------------|
| The requested user ID does not exist in the database. | `App\Exception\UserException::notFound`   | App\Service\UserService::getUser:18  |
| Duplicate entry for unique email field.               | `App\Exception\UserException::emailTaken` | App\Service\UserService::register:27 |
