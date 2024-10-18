<?php

namespace App\Controller\Amqp\PublishTweet;

use App\Application\RabbitMq\AbstractConsumer;
use App\Controller\Amqp\PublishTweet\Input\Message;
use App\Domain\Model\TweetModel;
use App\Domain\Service\FeedService;

class Consumer extends AbstractConsumer
{
    public function __construct(
        private readonly FeedService $feedService,
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
        $this->feedService->spreadTweetSync($tweet);

        return self::MSG_ACK;
    }
}
