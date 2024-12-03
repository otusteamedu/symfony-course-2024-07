<?php

namespace App\Domain\Query\GetFeed;

use App\Domain\Repository\FeedRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class Handler
{
    public function __construct(
        private readonly FeedRepositoryInterface $feedRepository,
    ) {
    }

    public function __invoke(Query $query): Result
    {
        return new Result(
            $this->feedRepository->ensureFeed($query->userId, $query->count),
        );
    }
}
