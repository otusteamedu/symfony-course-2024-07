<?php

namespace App\Controller\Web\AddFollowers\v1;

use App\Controller\Web\AddFollowers\v1\Input\AddFollowersDTO;
use App\Domain\DTO\AddFollowersDTO as InternalAddFollowersDTO;
use App\Domain\Entity\User;
use App\Domain\Service\FollowerService;

class Manager
{
    public function __construct(private readonly FollowerService $followerService)
    {
    }

    public function addFollowers(User $author, AddFollowersDTO $addFollowersDTO): int
    {
        return $addFollowersDTO->async ?
            $this->followerService->addFollowersAsync(
                new InternalAddFollowersDTO(
                    $author->getId(),
                    $addFollowersDTO->followerLoginPrefix,
                    $addFollowersDTO->count
                )
            ) :
            $this->followerService->addFollowersSync(
                $author,
                $addFollowersDTO->followerLoginPrefix,
                $addFollowersDTO->count
            );
    }
}
