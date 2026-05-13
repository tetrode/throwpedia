<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

class UserException extends RuntimeException
{
    public static function notFound(int $userId): self
    {
        return new self(\sprintf('User with ID %d not found.', $userId));
    }

    public static function emailTaken(string $email): self
    {
        return new self(\sprintf('Email %s is already taken.', $email));
    }
}
