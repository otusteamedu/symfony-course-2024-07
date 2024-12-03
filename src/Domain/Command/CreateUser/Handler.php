<?php

namespace App\Domain\Command\CreateUser;

use App\Domain\Entity\EmailUser;
use App\Domain\Entity\PhoneUser;
use App\Domain\Event\UserIsCreatedEvent;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\CommunicationChannelEnum;
use App\Domain\ValueObject\UserLogin;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsMessageHandler]
class Handler
{
    public function __construct(
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly UserRepositoryInterface $userRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(Command $command): void
    {
        $user = match($command->createUserModel->communicationChannel) {
            CommunicationChannelEnum::Email => (new EmailUser())->setEmail($command->createUserModel->communicationMethod),
            CommunicationChannelEnum::Phone => (new PhoneUser())->setPhone($command->createUserModel->communicationMethod),
        };
        $user->setLogin(UserLogin::fromString($command->createUserModel->login));
        $user->setPassword($this->userPasswordHasher->hashPassword($user, $command->createUserModel->password));
        $user->setAge($command->createUserModel->age);
        $user->setIsActive($command->createUserModel->isActive);
        $user->setRoles($command->createUserModel->roles);
        $this->userRepository->create($user);
        $this->eventDispatcher->dispatch(new UserIsCreatedEvent($user->getId(), $user->getLogin()));
    }
}
