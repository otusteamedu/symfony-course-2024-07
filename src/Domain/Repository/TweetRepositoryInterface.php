<?php

namespace App\Domain\Repository;

use App\Domain\Entity\Tweet;
use App\Domain\Model\TweetModel;

interface TweetRepositoryInterface
{
    public function create(Tweet $tweet): int;

    /**
     * @return TweetModel[]
     */
    public function getTweetsPaginated(int $page, int $perPage): array;
}
