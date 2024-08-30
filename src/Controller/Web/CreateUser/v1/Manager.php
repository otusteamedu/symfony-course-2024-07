<?php

namespace App\Controller\Web\CreateUser\v1;

use App\Domain\Entity\User;
use App\Domain\Service\UserService;

class Manager
{
    public function __construct(private readonly UserService $userService)
    {
    }

    public function create(string $login, ?string $phone = null, ?string $email = null): ?User
    {
        if ($phone !== null) {
            return $this->userService->createWithPhone($login, $phone);
        }

        if ($email !== null) {
            return $this->userService->createWithEmail($login, $email);
        }

        return null;
    }
}
