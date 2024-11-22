<?php

namespace App\Domain\Service;

use App\Domain\Entity\User;
use FeedBundle\Domain\Service\FeedService;

class FeedFacade implements FeedServiceInterface
{
    public function __construct(
        private readonly FeedService $feedService,
    ) {

    }

    public function ensureFeed(User $user, int $count): array
    {
        return $this->feedService->ensureFeed($user->getId(), $count);
    }
}
