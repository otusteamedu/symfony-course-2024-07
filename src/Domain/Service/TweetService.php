<?php

namespace App\Domain\Service;

use App\Domain\Entity\Tweet;
use App\Domain\Entity\User;
use App\Domain\Model\TweetModel;
use App\Domain\Repository\TweetRepositoryInterface;

class TweetService
{
    public function __construct(
        private readonly TweetRepositoryInterface $tweetRepository,
        private readonly FeedService $feedService,
    ) {
    }

    public function postTweet(User $author, string $text, bool $async): void
    {
        $tweet = new Tweet();
        $tweet->setAuthor($author);
        $tweet->setText($text);
        $author->addTweet($tweet);
        $this->tweetRepository->create($tweet);
        $tweetModel = new TweetModel(
            $tweet->getId(),
            $tweet->getAuthor()->getLogin(),
            $tweet->getAuthor()->getId(),
            $tweet->getText(),
            $tweet->getCreatedAt()
        );
        if ($async) {
            $this->feedService->spreadTweetAsync($tweetModel);
        } else {
            $this->feedService->spreadTweetSync($tweetModel);
        }
    }

    /**
     * @return TweetModel[]
     */
    public function getTweetsPaginated(int $page, int $perPage): array
    {
        return $this->tweetRepository->getTweetsPaginated($page, $perPage);
    }
}
