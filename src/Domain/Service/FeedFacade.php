<?php

namespace App\Domain\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\FeedRepositoryInterface;

class FeedFacade implements FeedServiceInterface
{
    public function __construct(
        private readonly FeedRepositoryInterface $feedRepository,
    ) {

    }

    public function ensureFeed(User $user, int $count): array
    {
        return $this->feedRepository->ensureFeed($user->getId(), $count);
    }
}
