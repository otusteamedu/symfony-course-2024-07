<?php

namespace App\Domain\EventSubscriber;

use App\Domain\Event\CreateUserEvent;
use App\Domain\Event\UserIsCreatedEvent;
use App\Domain\Service\UserService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UserEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly UserService $userService,
        private readonly LoggerInterface $elasticsearchLogger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CreateUserEvent::class => 'onCreateUser',
            UserIsCreatedEvent::class => 'onUserIsCreated',
        ];
    }

    public function onCreateUser(CreateUserEvent $event): void
    {
        $user = null;

        if ($event->phone !== null) {
            $user = $this->userService->createWithPhone($event->login, $event->phone);
        } elseif ($event->email !== null) {
            $user = $this->userService->createWithEmail($event->login, $event->email);
        }

        $event->id = $user?->getId();

    }

    public function onUserIsCreated(UserIsCreatedEvent $event): void
    {
        $this->elasticsearchLogger->info("User is created: id {$event->id}, login {$event->login}");
    }
}
