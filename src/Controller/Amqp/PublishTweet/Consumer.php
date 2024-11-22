<?php

namespace App\Controller\Amqp\PublishTweet;

use App\Application\RabbitMq\AbstractConsumer;
use App\Controller\Amqp\PublishTweet\Input\Message;
use App\Domain\Bus\UpdateFeedBusInterface;
use App\Domain\DTO\UpdateFeedDTO;
use App\Domain\Entity\EmailUser;
use App\Domain\Service\SubscriptionService;
use App\Domain\ValueObject\CommunicationChannelEnum;

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
                $follower instanceof EmailUser ?
                    CommunicationChannelEnum::Email->value : CommunicationChannelEnum::Phone->value,
            );
            $this->updateFeedBus->sendUpdateFeedMessage($updateFeedDTO);
        }

        return self::MSG_ACK;
    }
}
