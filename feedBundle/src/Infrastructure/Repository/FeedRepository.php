<?php

namespace FeedBundle\Infrastructure\Repository;

use FeedBundle\Domain\Entity\Feed;
use FeedBundle\Domain\Model\TweetModel;

class FeedRepository extends AbstractRepository
{
    public function putTweetToReaderFeed(TweetModel $tweet, int $readerId): bool
    {
        $feed = $this->ensureFeedForReader($readerId);
        if ($feed === null) {
            return false;
        }
        $tweets = $feed->getTweets();
        $tweets[] = $tweet->toFeed();
        $feed->setTweets($tweets);
        $this->flush();

        return true;
    }

    public function ensureFeedForReader(int $readerId): ?Feed
    {
        $feedRepository = $this->entityManager->getRepository(Feed::class);
        $feed = $feedRepository->findOneBy(['readerId' => $readerId]);
        if (!($feed instanceof Feed)) {
            $feed = new Feed();
            $feed->setReaderId($readerId);
            $feed->setTweets([]);
            $this->store($feed);
        }

        return $feed;
    }
}
