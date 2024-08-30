<?php

namespace App\Controller\Web\DeleteUser\v1;

use App\Domain\Service\UserService;

class Manager
{
    public function __construct(private readonly UserService $userService)
    {
    }

    public function deleteUserById(int $userId): bool
    {
        return $this->userService->removeById($userId);
    }
}
