<?php

namespace App\Controller\Web\GetTweet\v1;

use App\Controller\Web\GetTweet\v1\Output\TweetDTO;
use App\Domain\Entity\Tweet;
use App\Domain\Service\TweetService;

class Manager
{
    public function __construct(private readonly TweetService $tweetService)
    {
    }

    /**
     * @return Tweet[]
     */
    public function getTweetsPaginated(int $page, int $perPage): array
    {
        return array_map(
            static fn (Tweet $tweet) => new TweetDTO(
                $tweet->getId(),
                $tweet->getText(),
                $tweet->getAuthor()->getLogin(),
                $tweet->getCreatedAt()->format('Y-m-d H:i:s'),
            ),
            $this->tweetService->getTweetsPaginated($page, $perPage)
        );
    }
}
