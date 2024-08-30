<?php

namespace App\Controller\Web\UpdateUserLogin\v1;

use App\Domain\Entity\User;
use App\Domain\Service\UserService;

class Manager
{
    public function __construct(private readonly UserService $userService)
    {
    }

    public function updateUserLogin(int $userId, string $login): bool
    {
        $user = $this->userService->updateUserLogin($userId, $login);

        return $user instanceof User;
    }
}
