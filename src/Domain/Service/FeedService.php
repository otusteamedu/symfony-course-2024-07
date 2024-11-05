<?php

namespace App\Domain\Service;

use App\Domain\Bus\PublishTweetBusInterface;
use App\Domain\Bus\SendNotificationBusInterface;
use App\Domain\DTO\SendNotificationDTO;
use App\Domain\Entity\EmailUser;
use App\Domain\Entity\Subscription;
use App\Domain\Entity\Tweet;
use App\Domain\Entity\User;
use App\Domain\Model\TweetModel;
use App\Domain\ValueObject\CommunicationChannelEnum;
use App\Infrastructure\Repository\FeedRepository;
use App\Infrastructure\Repository\TweetRepository;

class FeedService
{
    public function __construct(
        private readonly FeedRepository $feedRepository,
        private readonly SubscriptionService $subscriptionService,
        private readonly PublishTweetBusInterface $publishTweetBus,
        private readonly SendNotificationBusInterface $sendNotificationBus,
        private readonly TweetRepository $tweetRepository,
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
            $this->materializeTweet($tweet, $follower);
        }
    }

    public function materializeTweet(TweetModel $tweet, User $follower): void
    {
        $this->feedRepository->putTweetToReaderFeed($tweet, $follower);
        $sendNotificationDTO = new SendNotificationDTO(
            $follower->getId(),
            $tweet->text,
            $follower instanceof EmailUser ? CommunicationChannelEnum::Email : CommunicationChannelEnum::Phone
        );
        $this->sendNotificationBus->sendNotification($sendNotificationDTO);
    }

    /**
     * @return Tweet[]
     */
    public function getFeedWithoutMaterialization(User $user, int $count): array
    {
        return $this->tweetRepository->getTweetsForAuthorIds(
            array_map(
                static fn (Subscription $subscription): int => $subscription->getAuthor()->getId(),
                $user->getSubscriptionAuthors()
            ),
            $count,
        );
    }
}
