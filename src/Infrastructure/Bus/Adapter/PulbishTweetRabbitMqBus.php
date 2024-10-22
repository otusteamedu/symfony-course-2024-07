<?php

namespace App\Infrastructure\Bus\Adapter;

use App\Domain\Bus\PublishTweetBusInterface;
use App\Domain\Model\TweetModel;
use App\Infrastructure\Bus\AmqpExchangeEnum;
use App\Infrastructure\Bus\RabbitMqBus;

class PulbishTweetRabbitMqBus implements PublishTweetBusInterface
{
    public function __construct(private readonly RabbitMqBus $rabbitMqBus)
    {
    }

    public function sendPublishTweetMessage(TweetModel $tweetModel): bool
    {
        return $this->rabbitMqBus->publishToExchange(AmqpExchangeEnum::PublishTweet, $tweetModel);
    }
}
