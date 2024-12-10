<?php

namespace App\Domain\Repository;

use App\Domain\Entity\User;
use Doctrine\ORM\NonUniqueResultException;

interface UserRepositoryInterface
{
    public function create(User $user): int;

    public function subscribeUser(User $author, User $follower): void;

    /**
     * @return User[]
     */
    public function findUsersByLogin(string $name): array;

    /**
     * @return User[]
     */
    public function findUsersByLoginWithCriteria(string $login): array;

    public function find(int $userId): ?User;

    public function updateLogin(User $user, string $login): void;

    public function updateAvatarLink(User $user, string $avatarLink): void;

    public function findUsersByLoginWithQueryBuilder(string $login): array;

    public function updateUserLoginWithQueryBuilder(int $userId, string $login): void;

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function updateUserLoginWithDBALQueryBuilder(int $userId, string $login): void;

    /**
     * @throws NonUniqueResultException
     */
    public function findUserWithTweetsWithQueryBuilder(int $userId): array;

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function findUserWithTweetsWithDBALQueryBuilder(int $userId): array;

    public function remove(User $user): void;

    public function removeInFuture(User $user, DateInterval $dateInterval): void;

    /**
     * @return User[]
     */
    public function findUsersByLoginWithDeleted(string $name): array;

    /**
     * @return User[]
     */
    public function findAll(): array;

    public function updateUserToken(User $user): string;

    public function findUserByToken(string $token): ?User;

    public function clearUserToken(User $user): void;

    /**
     * @return User[]
     */
    public function findUsersByQuery(string $query, int $perPage, int $page): array;
}
