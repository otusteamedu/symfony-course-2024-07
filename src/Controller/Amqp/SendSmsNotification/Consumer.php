<?php

namespace App\Controller\Amqp\SendSmsNotification;

use App\Application\RabbitMq\AbstractConsumer;
use App\Controller\Amqp\SendSmsNotification\Input\Message;
use App\Domain\Entity\PhoneUser;
use App\Domain\Service\SmsNotificationService;
use App\Domain\Service\UserService;

class Consumer extends AbstractConsumer
{
    public function __construct(
        private readonly UserService $userService,
        private readonly SmsNotificationService $emailNotificationService,
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
        if (!($user instanceof PhoneUser)) {
            return $this->reject(sprintf('User ID %s was not found or does not use phone', $message->userId));
        }

        $this->emailNotificationService->saveSmsNotification($user->getPhone(), $message->text);

        return self::MSG_ACK;
    }
}
