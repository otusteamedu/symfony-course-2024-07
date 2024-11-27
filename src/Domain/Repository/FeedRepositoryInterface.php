<?php

namespace App\Domain\Repository;

use App\Domain\Entity\User;

interface FeedRepositoryInterface
{
    public function ensureFeed(int $userId, int $count): array;
}
