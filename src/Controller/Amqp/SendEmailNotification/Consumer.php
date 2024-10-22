<?php

namespace App\Controller\Amqp\SendEmailNotification;

use App\Application\RabbitMq\AbstractConsumer;
use App\Controller\Amqp\SendEmailNotification\Input\Message;
use App\Domain\Entity\EmailUser;
use App\Domain\Service\EmailNotificationService;
use App\Domain\Service\UserService;

class Consumer extends AbstractConsumer
{
    public function __construct(
        private readonly UserService $userService,
        private readonly EmailNotificationService $emailNotificationService,
    ) {
    }

    protected function getMessageClass(): string
    {
        return Message::class;
    }

    /**
     * @param Message $message
     */
    protected function handle($message): int
    {
        $user = $this->userService->findUserById($message->userId);
        if (!($user instanceof EmailUser)) {
            return $this->reject(sprintf('User ID %s was not found or does not use email', $message->userId));
        }

        $this->emailNotificationService->saveEmailNotification($user->getEmail(), $message->text);

        return self::MSG_ACK;
    }
}
