<?php

namespace App\Controller\Amqp\AddFollowers;

use App\Application\RabbitMq\AbstractConsumer;
use App\Controller\Amqp\AddFollowers\Input\Message;
use App\Domain\Entity\User;
use App\Domain\Service\FollowerService;
use App\Domain\Service\UserService;

class Consumer extends AbstractConsumer
{
    public function __construct(
        private readonly UserService $userService,
        private readonly FollowerService $followerService,
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
        if (!($user instanceof User)) {
            return $this->reject(sprintf('User ID %s was not found', $message->userId));
        }

        if ($message->count === 5) {
            sleep(1000);
        }

        $this->followerService->addFollowersSync($user, $message->followerLogin, $message->count);

        return self::MSG_ACK;
    }
}
