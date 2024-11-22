<?php

namespace FeedBundle\Infrastructure\Bus\Adapter;

use FeedBundle\Domain\Bus\SendNotificationBusInterface;
use FeedBundle\Domain\DTO\SendNotificationDTO;
use FeedBundle\Infrastructure\Bus\AmqpExchangeEnum;
use FeedBundle\Infrastructure\Bus\RabbitMqBus;

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
            $sendNotificationDTO->channel
        );
    }
}
