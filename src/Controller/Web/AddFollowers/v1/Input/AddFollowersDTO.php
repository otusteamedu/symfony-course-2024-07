<?php

namespace App\Controller\Web\AddFollowers\v1\Input;

class AddFollowersDTO
{
    public function __construct(
        public readonly string $followerLoginPrefix,
        public readonly int $count,
        public readonly bool $async = false,
    ) {
    }
}
