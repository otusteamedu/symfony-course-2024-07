<?php

namespace App\Controller\Web\AddFollowers\v1;

use App\Controller\Web\AddFollowers\v1\Input\AddFollowersDTO;
use App\Domain\Entity\User;
use App\Domain\Service\FollowerService;

class Manager
{
    public function __construct(private readonly FollowerService $followerService)
    {
    }

    public function addFollowers(User $author, AddFollowersDTO $addFollowersDTO): int
    {
        return $this->followerService->addFollowers($author, $addFollowersDTO->followerLoginPrefix, $addFollowersDTO->count);
    }
}
