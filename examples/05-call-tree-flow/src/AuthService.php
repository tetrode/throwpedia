<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\UserRepository;

class AuthService
{
    public function authenticate(int $id): void
    {
        $repo = new UserRepository();
        $repo->find($id);
    }
}
