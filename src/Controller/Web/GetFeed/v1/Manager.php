<?php

namespace App\Controller\Web\GetFeed\v1;

use App\Controller\Web\GetFeed\v1\Output\Response;
use App\Controller\Web\GetFeed\v1\Output\TweetDTO;
use App\Domain\Entity\User;
use FeedBundle\Domain\Service\FeedService;

class Manager
{
    private const DEFAULT_FEED_SIZE = 20;

    public function __construct(private readonly FeedService $feedService)
    {
    }

    public function getFeed(User $user, ?int $count = null): Response
    {
        return new Response(
            array_map(
                static fn (array $tweetData): TweetDTO => new TweetDTO(...$tweetData),
                $this->feedService->ensureFeed($user, $count ?? self::DEFAULT_FEED_SIZE),
            )
        );
    }
}
