<?php

declare(strict_types=1);

namespace App\Service;

use App\Attribute\ExReason;
use App\Exception\UserException;

class UserService
{
    #[ExReason(
        theReason: 'The requested user ID does not exist in the database.',
    )]
    public function getUser(int $id): void
    {
        // ... some logic
        throw UserException::notFound($id);
    }

    #[ExReason(
        theReason: 'Duplicate entry for unique email field.',
    )]
    public function register(string $email): void
    {
        // ... some logic
        throw UserException::emailTaken($email);
    }
}
