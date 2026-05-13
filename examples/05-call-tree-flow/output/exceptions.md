# Business Exceptions Catalog

**Project:** unknown (unknown)
**Exceptions found:** 2

**Throwpedia Version:** 0.2.0
**Scan time:** 2026-05-13 11:18:26

## ExceptionReason

| Identifier | Technical Reason | Business Reason | Exception | Thrown From |
| ---------- | ---------------- | --------------- | --------- | ----------- |
| DIRECT_NEW_EXCEPTION | Thrown from App\Repository\UserRepository::find:19 | Direct instantiation of Exception | `Exception` | App\Repository\UserRepository::find:19 |
| USER_NOT_FOUND | The requested user ID does not exist in the database. | The user you are looking for could not be found. | `Exception` | App\Repository\UserRepository::find:19 |

### Calling Trees

#### DIRECT_NEW_EXCEPTION

```
App\Controller\LoginController::login()
 └── App\Service\AuthService::authenticate()
      └── App\Repository\UserRepository::find()
               throws Exception

```

#### USER_NOT_FOUND

```
App\Controller\LoginController::login()
 └── App\Service\AuthService::authenticate()
      └── App\Repository\UserRepository::find()
               throws Exception

```
