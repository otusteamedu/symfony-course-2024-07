<?php

namespace App\Controller\Web\CreateUser\v1;

use App\Controller\Web\CreateUser\v1\Input\CreateUserDTO;
use App\Domain\Entity\User;
use App\Domain\Event\CreateUserEvent;
use App\Domain\Service\UserService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Manager
{
    public function __construct(
        private readonly UserService $userService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function create(CreateUserDTO $createUserDTO): ?User
    {
        $event = new CreateUserEvent($createUserDTO->login, $createUserDTO->phone, $createUserDTO->email);
        $event = $this->eventDispatcher->dispatch($event);

        return $event->id === null ? null : $this->userService->findUserById($event->id);
    }
}
