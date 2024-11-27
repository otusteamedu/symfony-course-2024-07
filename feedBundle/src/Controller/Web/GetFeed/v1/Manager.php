<?php

namespace FeedBundle\Controller\Web\GetFeed\v1;

use FeedBundle\Controller\Web\GetFeed\v1\Output\Response;
use FeedBundle\Controller\Web\GetFeed\v1\Output\TweetDTO;
use FeedBundle\Domain\Service\FeedService;

class Manager
{
    private const DEFAULT_FEED_SIZE = 20;

    public function __construct(private readonly FeedService $feedService)
    {
    }

    public function getFeed(int $userId, ?int $count = null): Response
    {
        return new Response(
            array_map(
                static fn (array $tweetData): TweetDTO => new TweetDTO(...$tweetData),
                $this->feedService->ensureFeed($userId, $count ?? self::DEFAULT_FEED_SIZE),
            )
        );
    }
}
