<?php

namespace FeedBundle\Domain\Service;

use FeedBundle\Domain\Bus\SendNotificationBusInterface;
use FeedBundle\Domain\DTO\SendNotificationDTO;
use FeedBundle\Domain\Model\TweetModel;
use FeedBundle\Infrastructure\Repository\FeedRepository;

class FeedService
{
    public function __construct(
        private readonly FeedRepository $feedRepository,
        private readonly SendNotificationBusInterface $sendNotificationBus,
    ) {
    }

    public function ensureFeed(int $userId, int $count): array
    {
        $feed = $this->feedRepository->ensureFeedForReader($userId);

        return $feed === null ? [] : array_slice($feed->getTweets(), -$count);
    }

    public function materializeTweet(TweetModel $tweet, int $followerId, string $channel): void
    {
        $this->feedRepository->putTweetToReaderFeed($tweet, $followerId);
        $sendNotificationDTO = new SendNotificationDTO(
            $followerId,
            $tweet->text,
            $channel
        );
        $this->sendNotificationBus->sendNotification($sendNotificationDTO);
    }
}
