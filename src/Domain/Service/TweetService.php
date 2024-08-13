<?php

namespace App\Domain\Service;

use App\Domain\Entity\Tweet;
use App\Domain\Entity\User;
use App\Infrastructure\Repository\TweetRepository;

class TweetService
{
    public function __construct(private readonly TweetRepository $tweetRepository)
    {
    }

    public function postTweet(User $author, string $text): void
    {
        $tweet = new Tweet();
        $tweet->setAuthor($author);
        $tweet->setText($text);
        $tweet->setCreatedAt();
        $tweet->setUpdatedAt();
        $this->tweetRepository->create($tweet);
    }
}
