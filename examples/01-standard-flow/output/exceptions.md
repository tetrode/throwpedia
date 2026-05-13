# Business Exceptions Catalog

**Project:** unknown (unknown)
**Exceptions found:** 2

**Throwpedia Version:** 0.2.0
**Scan time:** 2026-05-13 11:18:26

## ExceptionReason

| Unique Identifier | Technical Reason | Business Reason | Exception | Thrown From |
| ----------------- | ---------------- | --------------- | --------- | ----------- |
| USER_ALREADY_EXISTS | Duplicate entry for unique email field. | An account with this email already exists. | `App\Exception\UserException::emailTaken` | App\Service\UserService::register:31 |
| USER_NOT_FOUND | The requested user ID does not exist in the database. | The user you are looking for could not be found. | `App\Exception\UserException::notFound` | App\Service\UserService::getUser:20 |

### Calling Trees

#### USER_ALREADY_EXISTS

```
App\Service\UserService::register()
     throws App\Exception\UserException::emailTaken

```

#### USER_NOT_FOUND

```
App\Service\UserService::getUser()
     throws App\Exception\UserException::notFound

```
