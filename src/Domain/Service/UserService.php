<?php

namespace App\Domain\Service;

use App\Domain\Entity\User;
use App\Infrastructure\Repository\UserRepository;

class UserService
{
    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    public function create(string $login): User
    {
        $user = new User();
        $user->setLogin($login);
        $this->userRepository->create($user);

        return $user;
    }

    public function refresh(User $user): void
    {
        $this->userRepository->refresh($user);
    }

    public function subscribeUser(User $author, User $follower): void
    {
        $this->userRepository->subscribeUser($author, $follower);
    }

    /**
     * @return User[]
     */
    public function findUsersByLogin(string $login): array
    {
        return $this->userRepository->findUsersByLogin($login);
    }

    /**
     * @return User[]
     */
    public function findUsersByLoginWithCriteria(string $login): array
    {
        return $this->userRepository->findUsersByLoginWithCriteria($login);
    }

    public function updateUserLogin(int $userId, string $login): ?User
    {
        $user = $this->userRepository->find($userId);
        if (!($user instanceof User)) {
            return null;
        }
        $this->userRepository->updateLogin($user, $login);

        return $user;
    }

    public function findUsersByLoginWithQueryBuilder(string $login): array
    {
        return $this->userRepository->findUsersByLoginWithQueryBuilder($login);
    }

    public function updateUserLoginWithQueryBuilder(int $userId, string $login): ?User
    {
        $user = $this->userRepository->find($userId);
        if (!($user instanceof User)) {
            return null;
        }
        $this->userRepository->updateUserLoginWithQueryBuilder($user->getId(), $login);
        $this->userRepository->refresh($user);

        return $user;
    }

    public function updateUserLoginWithDBALQueryBuilder(int $userId, string $login): ?User
    {
        $user = $this->userRepository->find($userId);
        if (!($user instanceof User)) {
            return null;
        }
        $this->userRepository->updateUserLoginWithDBALQueryBuilder($user->getId(), $login);
        $this->userRepository->refresh($user);

        return $user;
    }

    public function findUserWithTweetsWithQueryBuilder(int $userId): array
    {
        return $this->userRepository->findUserWithTweetsWithQueryBuilder($userId);
    }
}
