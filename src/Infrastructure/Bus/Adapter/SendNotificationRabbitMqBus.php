<?php

namespace App\Infrastructure\Bus\Adapter;

use App\Domain\Bus\SendNotificationBusInterface;
use App\Domain\DTO\SendNotificationDTO;
use App\Infrastructure\Bus\AmqpExchangeEnum;
use App\Infrastructure\Bus\RabbitMqBus;

class SendNotificationRabbitMqBus implements SendNotificationBusInterface
{
    public function __construct(private readonly RabbitMqBus $rabbitMqBus)
    {
    }

    public function sendNotification(SendNotificationDTO $sendNotificationDTO): bool
    {
        return $this->rabbitMqBus->publishToExchange(
            AmqpExchangeEnum::SendNotification,
            $sendNotificationDTO,
            $sendNotificationDTO->channel->value
        );
    }
}
