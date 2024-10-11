<?php

namespace App\Infrastructure\Repository;

use App\Domain\Entity\Tweet;
use App\Domain\Model\TweetModel;
use App\Domain\Repository\TweetRepositoryInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

class TweetRepositoryCacheDecorator implements TweetRepositoryInterface
{
    public function __construct(
        private readonly TweetRepository $tweetRepository,
        private readonly CacheItemPoolInterface $cacheItemPool,
    ) {
    }

    public function create(Tweet $tweet): int
    {
        return $this->tweetRepository->create($tweet);
    }

    /**
     * @return TweetModel[]
     * @throws InvalidArgumentException
     */
    public function getTweetsPaginated(int $page, int $perPage): array
    {
        $tweetsItem = $this->cacheItemPool->getItem($this->getCacheKey($page, $perPage));
        if (!$tweetsItem->isHit()) {
            $tweets = $this->tweetRepository->getTweetsPaginated($page, $perPage);
            $tweetsItem->set(
                array_map(
                    static fn (Tweet $tweet): TweetModel => new TweetModel(
                        $tweet->getId(),
                        $tweet->getAuthor()->getLogin(),
                        $tweet->getText(),
                        $tweet->getCreatedAt(),
                    ),
                    $tweets
                )
            );
            $this->cacheItemPool->save($tweetsItem);
        }

        return $tweetsItem->get();
    }

    private function getCacheKey(int $page, int $perPage): string
    {
        return "tweets_{$page}_$perPage";
    }
}
