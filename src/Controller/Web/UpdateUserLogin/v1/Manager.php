<?php

namespace App\Controller\Web\UpdateUserLogin\v1;

use App\Domain\Entity\User;
use App\Domain\Service\UserService;

class Manager
{
    public function __construct(private readonly UserService $userService)
    {
    }

    public function updateLogin(User $user, string $login): void
    {
        $this->userService->updateLogin($user, $login);
    }
}
