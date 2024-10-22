<?php

namespace App\Infrastructure\Repository;

use App\Domain\Entity\Feed;
use App\Domain\Entity\User;
use App\Domain\Model\TweetModel;

class FeedRepository extends AbstractRepository
{
    public function putTweetToReaderFeed(TweetModel $tweet, User $reader): bool
    {
        $feed = $this->ensureFeedForReader($reader);
        if ($feed === null) {
            return false;
        }
        $tweets = $feed->getTweets();
        $tweets[] = $tweet->toFeed();
        $feed->setTweets($tweets);
        $this->flush();

        return true;
    }

    public function ensureFeedForReader(User $reader): ?Feed
    {
        $feedRepository = $this->entityManager->getRepository(Feed::class);
        $feed = $feedRepository->findOneBy(['reader' => $reader]);
        if (!($feed instanceof Feed)) {
            $feed = new Feed();
            $feed->setReader($reader);
            $feed->setTweets([]);
            $this->store($feed);
        }

        return $feed;
    }
}
