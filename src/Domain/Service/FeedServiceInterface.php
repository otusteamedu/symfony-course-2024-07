<?php

namespace App\Domain\Service;

use App\Domain\Entity\User;

interface FeedServiceInterface
{
    public function ensureFeed(User $user, int $count): array;
}
