<?php

namespace FeedBundle\Controller\Amqp\UpdateFeed;

use FeedBundle\Application\RabbitMq\AbstractConsumer;
use FeedBundle\Domain\Model\TweetModel;
use FeedBundle\Controller\Amqp\UpdateFeed\Input\Message;
use FeedBundle\Domain\Service\FeedService;
use StatsdBundle\Storage\MetricsStorageInterface;

class Consumer extends AbstractConsumer
{
    public function __construct(
        private readonly FeedService $feedService,
        private readonly MetricsStorageInterface $metricsStorage,
        private readonly string $key,
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
        $this->feedService->materializeTweet($tweet, $message->followerId, $message->followerChannel);
        $this->metricsStorage->increment($this->key);

        return self::MSG_ACK;
    }
}
