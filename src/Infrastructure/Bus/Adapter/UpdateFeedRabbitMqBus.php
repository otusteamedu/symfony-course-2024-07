<?php

namespace App\Infrastructure\Bus\Adapter;

use App\Domain\Bus\UpdateFeedBusInterface;
use App\Domain\DTO\UpdateFeedDTO;
use App\Infrastructure\Bus\AmqpExchangeEnum;
use App\Infrastructure\Bus\RabbitMqBus;

class UpdateFeedRabbitMqBus implements UpdateFeedBusInterface
{
    public function __construct(private readonly RabbitMqBus $rabbitMqBus)
    {
    }

    public function sendUpdateFeedMessage(UpdateFeedDTO $updateFeedDTO): bool
    {
        return $this->rabbitMqBus->publishToExchange(
            AmqpExchangeEnum::UpdateFeed,
            $updateFeedDTO,
            (string)$updateFeedDTO->followerId
        );
    }
}
