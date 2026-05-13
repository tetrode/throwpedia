<?php

declare(strict_types=1);

namespace App\Repository;

use Tetrode\Throwpedia\Attributes\ExceptionReason;

class UserRepository
{
    #[ExceptionReason(
        identifier: 'USER_NOT_FOUND',
        technicalReason: 'The requested user ID does not exist in the database.',
        businessReason: 'The user you are looking for could not be found.'
    )]
    public function find(int $id): void
    {
        // ... some logic
        throw new \Exception("User $id not found");
    }
}
