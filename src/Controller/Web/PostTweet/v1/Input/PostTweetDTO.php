<?php

namespace App\Controller\Web\PostTweet\v1\Input;

class PostTweetDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly string $text,
        public readonly bool $async = false,
    ) {
    }
}
