<?php

namespace App\Domain\DTO;

class AddFollowersDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly string $followerLogin,
        public readonly int $count
    ) {
    }
}
