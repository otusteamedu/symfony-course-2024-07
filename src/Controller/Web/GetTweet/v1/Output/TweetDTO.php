<?php

namespace App\Controller\Web\GetTweet\v1\Output;

class TweetDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $text,
        public readonly string $author,
        public readonly string $createdAt
    ) {
    }
}
