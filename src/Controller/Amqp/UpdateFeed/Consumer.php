<?php

namespace App\Controller\Amqp\UpdateFeed;

use App\Application\RabbitMq\AbstractConsumer;
use App\Controller\Amqp\UpdateFeed\Input\Message;
use App\Domain\Entity\User;
use App\Domain\Model\TweetModel;
use App\Domain\Service\FeedService;
use App\Domain\Service\UserService;

class Consumer extends AbstractConsumer
{
    public function __construct(
        private readonly FeedService $feedService,
        private readonly UserService $userService,
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
        $tweet = new TweetModel(
            $message->id,
            $message->author,
            $message->authorId,
            $message->text,
            $message->createdAt,
        );
        $user = $this->userService->findUserById($message->followerId);
        if (!($user instanceof User)) {
            $this->reject('User {$message->followerId} was not found');
        }
        $this->feedService->materializeTweet($tweet, $user);

        return self::MSG_ACK;
    }
}
