<?php

namespace App\Controller\Web\GetFeed\v1;

use App\Domain\Entity\User;
use App\Domain\Service\FeedService;

class Manager
{
    private const DEFAULT_FEED_SIZE = 20;

    public function __construct(private readonly FeedService $feedService)
    {
    }

    public function getFeed(User $user, ?int $count = null): array
    {
        return $this->feedService->ensureFeed($user, $count ?? self::DEFAULT_FEED_SIZE);
    }
}
