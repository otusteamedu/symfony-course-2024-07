<?php

namespace App\Infrastructure\Repository;

use App\Domain\Entity\User;

/**
 * @extends AbstractRepository<User>
 */
class UserRepository extends AbstractRepository
{
    public function create(User $user): int
    {
        return $this->store($user);
    }
}
