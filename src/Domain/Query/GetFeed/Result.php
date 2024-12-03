<?php

namespace App\Domain\Query\GetFeed;

class Result
{
    public function __construct(
        public readonly array $tweets
    ) {
    }
}
