<?php

namespace App\Domain\Service;

use App\Domain\Entity\User;

class UserService
{
    public function create(string $login): User
    {
        $user = new User();
        $user->setLogin($login);
        $user->setCreatedAt();
        $user->setUpdatedAt();

        return $user;
    }
}
