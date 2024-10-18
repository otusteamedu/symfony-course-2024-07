<?php

namespace App\Domain\Service;

use App\Domain\Bus\PublishTweetBusInterface;
use App\Domain\Entity\User;
use App\Domain\Model\TweetModel;
use App\Infrastructure\Repository\FeedRepository;

class FeedService
{
    public function __construct(
        private readonly FeedRepository $feedRepository,
        private readonly SubscriptionService $subscriptionService,
        private readonly PublishTweetBusInterface $publishTweetBus,
    ) {
    }

    public function ensureFeed(User $user, int $count): array
    {
        $feed = $this->feedRepository->ensureFeedForReader($user);

        return $feed === null ? [] : array_slice($feed->getTweets(), -$count);
    }

    public function spreadTweetAsync(TweetModel $tweet): void
    {
        $this->publishTweetBus->sendPublishTweetMessage($tweet);
    }

    public function spreadTweetSync(TweetModel $tweet): void
    {
        $followers = $this->subscriptionService->getFollowers($tweet->authorId);

        foreach ($followers as $follower) {
            $this->feedRepository->putTweetToReaderFeed($tweet, $follower);
        }
    }
}
