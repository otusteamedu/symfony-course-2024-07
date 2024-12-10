<?php

namespace App\Controller\Web\GetFeed\v1;

use App\Application\Query\QueryBusInterface;
use App\Controller\Web\GetFeed\v1\Output\Response;
use App\Controller\Web\GetFeed\v1\Output\TweetDTO;
use App\Domain\Entity\User;
use App\Domain\Query\GetFeed\Query;
use App\Domain\Query\GetFeed\Result;

class Manager
{
    private const DEFAULT_FEED_SIZE = 20;

    /**
     * @param QueryBusInterface<Result> $queryBus
     */
    public function __construct(private readonly QueryBusInterface $queryBus)
    {
    }

    public function getFeed(User $user, ?int $count = null): Response
    {
        return new Response(
            array_map(
                static fn (array $tweetData): TweetDTO => new TweetDTO(...$tweetData),
                $this->queryBus->query(new Query($user->getId(), $count ?? self::DEFAULT_FEED_SIZE))->tweets,
            )
        );
    }
}
