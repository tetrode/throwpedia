<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AuthService;

class LoginController
{
    public function login(int $id): void
    {
        $auth = new AuthService();
        $auth->authenticate($id);
    }
}
