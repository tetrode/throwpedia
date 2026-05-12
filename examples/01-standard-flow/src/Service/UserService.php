<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\UserException;
use Tetrode\Throwpedia\Attributes\ExceptionReason;

class UserService
{
    #[ExceptionReason(
        identifier: 'USER_NOT_FOUND',
        technicalReason: 'The requested user ID does not exist in the database.',
        businessReason: 'The user you are looking for could not be found.'
    )]
    public function getUser(int $id): void
    {
        // ... some logic
        throw UserException::notFound($id);
    }

    #[ExceptionReason(
        identifier: 'USER_ALREADY_EXISTS',
        technicalReason: 'Duplicate entry for unique email field.',
        businessReason: 'An account with this email already exists.'
    )]
    public function register(string $email): void
    {
        // ... some logic
        throw UserException::emailTaken($email);
    }
}
