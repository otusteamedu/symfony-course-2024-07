<?php

namespace App\Domain\Service;

use App\Domain\Entity\EmailUser;
use App\Domain\Entity\PhoneUser;
use App\Domain\Entity\User;
use App\Domain\Model\CreateUserModel;
use App\Domain\ValueObject\CommunicationChannelEnum;
use App\Infrastructure\Repository\UserRepository;
use DateInterval;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
    ) {
    }

    public function create(CreateUserModel $createUserModel): User
    {
        $user = match($createUserModel->communicationChannel) {
            CommunicationChannelEnum::Email => (new EmailUser())->setEmail($createUserModel->communicationMethod),
            CommunicationChannelEnum::Phone => (new PhoneUser())->setPhone($createUserModel->communicationMethod),
        };
        $user->setLogin($createUserModel->login);
        $user->setPassword($this->userPasswordHasher->hashPassword($user, $createUserModel->password));
        $user->setAge($createUserModel->age);
        $user->setIsActive($createUserModel->isActive);
        $user->setRoles($createUserModel->roles);
        $this->userRepository->create($user);

        return $user;
    }

    public function createWithPhone(string $login, string $phone): User
    {
        $user = new PhoneUser();
        $user->setLogin($login);
        $user->setPhone($phone);
        $this->userRepository->create($user);

        return $user;
    }

    public function createWithEmail(string $login, string $email): User
    {
        $user = new EmailUser();
        $user->setLogin($login);
        $user->setEmail($email);
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

    public function findUserWithTweetsWithDBALQueryBuilder(int $userId): array
    {
        return $this->userRepository->findUserWithTweetsWithDBALQueryBuilder($userId);
    }

    public function removeById(int $userId): bool
    {
        $user = $this->userRepository->find($userId);
        if ($user instanceof User) {
            $this->userRepository->remove($user);

            return true;
        }

        return false;
    }

    public function removeByIdInFuture(int $userId, DateInterval $dateInterval): void
    {
        $user = $this->userRepository->find($userId);
        if ($user instanceof User) {
            $this->userRepository->removeInFuture($user, $dateInterval);
        }
    }

    /**
     * @return User[]
     */
    public function findUsersByLoginWithDeleted(string $login): array
    {
        return $this->userRepository->findUsersByLoginWithDeleted($login);
    }

    public function findUserById(int $id): ?User
    {
        return $this->userRepository->find($id);
    }

    /**
     * @return User[]
     */
    public function findAll(): array
    {
        return $this->userRepository->findAll();
    }

    public function remove(User $user): void
    {
        $this->userRepository->remove($user);
    }

    public function updateLogin(User $user, string $login): void
    {
        $this->userRepository->updateLogin($user, $login);
    }

    public function updateAvatarLink(User $user, string $avatarLink): void
    {
        $this->userRepository->updateAvatarLink($user, $avatarLink);
    }

    public function processFromForm(User $user): void
    {
        $this->userRepository->create($user);
    }

    public function findUserByLogin(string $login): ?User
    {
        $users = $this->userRepository->findUsersByLogin($login);

        return $users[0] ?? null;
    }

    public function updateUserToken(string $login): ?string
    {
        $user = $this->findUserByLogin($login);
        if ($user === null) {
            return null;
        }

        return $this->userRepository->updateUserToken($user);
    }

    public function findUserByToken(string $token): ?User
    {
        return $this->userRepository->findUserByToken($token);
    }
}
