<?php

namespace FeedBundle\Domain\MessageHandler\SendNotification;

use FeedBundle\Domain\DTO\SendNotificationAsyncDTO;
use FeedBundle\Domain\DTO\SendNotificationDTO;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class Handler
{
    public function __construct(private readonly MessageBusInterface $messageBus)
    {
    }

    public function __invoke(SendNotificationDTO $message): void
    {
        $envelope = new Envelope(
            new SendNotificationAsyncDTO($message->userId, $message->text, $message->channel),
            [new AmqpStamp($message->channel)]
        );
        $this->messageBus->dispatch($envelope);
    }
}
