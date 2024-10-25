<?php

namespace App\Controller\Web\GetUsersByQuery\v1;

use App\Domain\Entity\User;
use App\Domain\Service\UserService;

class Manager
{
    public function __construct(private readonly UserService $userService)
    {
    }

    /**
     * @return User[]
     */
    public function findUsersByQuery(string $query, int $perPage, int $page): array
    {
        return $this->userService->findUsersByQuery($query, $perPage, $page);
    }
}
