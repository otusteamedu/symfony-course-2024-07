<?php

namespace App\Controller\Amqp\PublishTweet;

use App\Application\RabbitMq\AbstractConsumer;
use App\Controller\Amqp\PublishTweet\Input\Message;
use App\Domain\Bus\UpdateFeedBusInterface;
use App\Domain\DTO\UpdateFeedDTO;
use App\Domain\Service\SubscriptionService;

class Consumer extends AbstractConsumer
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly UpdateFeedBusInterface $updateFeedBus,
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
        $followers = $this->subscriptionService->getFollowers($message->authorId);
        foreach ($followers as $follower) {
            $updateFeedDTO = new UpdateFeedDTO(
                $message->id,
                $message->author,
                $message->authorId,
                $message->text,
                $message->createdAt,
                $follower->getId(),
            );
            $this->updateFeedBus->sendUpdateFeedMessage($updateFeedDTO);
        }

        return self::MSG_ACK;
    }
}
