<?php

namespace FeedBundle\Infrastructure\Bus\Adapter;

use FeedBundle\Domain\Bus\SendNotificationBusInterface;
use FeedBundle\Domain\DTO\SendNotificationDTO;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class SendNotificationMessengerBus implements SendNotificationBusInterface
{
    public function __construct(private readonly MessageBusInterface $messageBus)
    {
    }

    public function sendNotification(SendNotificationDTO $sendNotificationDTO): bool
    {
        $this->messageBus->dispatch(
            new Envelope($sendNotificationDTO, [new AmqpStamp($sendNotificationDTO->channel)]),
        );

        return true;
    }
}
