<?php

namespace App\Domain\Service;

use App\Domain\Entity\Tweet;
use App\Domain\Entity\User;
use App\Domain\Model\TweetModel;
use App\Domain\Repository\TweetRepositoryInterface;

class TweetService
{
    public function __construct(private readonly TweetRepositoryInterface $tweetRepository)
    {
    }

    public function postTweet(User $author, string $text): void
    {
        $tweet = new Tweet();
        $tweet->setAuthor($author);
        $tweet->setText($text);
        $author->addTweet($tweet);
        $this->tweetRepository->create($tweet);
    }

    /**
     * @return TweetModel[]
     */
    public function getTweetsPaginated(int $page, int $perPage): array
    {
        return $this->tweetRepository->getTweetsPaginated($page, $perPage);
    }
}
