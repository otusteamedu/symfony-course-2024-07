<?php

namespace App\Controller\Web\GetTweet\v1;

use App\Controller\Web\GetTweet\v1\Output\TweetDTO;
use App\Domain\Model\TweetModel;
use App\Domain\Service\TweetService;

class Manager
{
    public function __construct(private readonly TweetService $tweetService)
    {
    }

    /**
     * @return TweetModel[]
     */
    public function getTweetsPaginated(int $page, int $perPage): array
    {
        return array_map(
            static fn (TweetModel $tweet) => new TweetDTO(
                $tweet->id,
                $tweet->text,
                $tweet->author,
                $tweet->createdAt->format('Y-m-d H:i:s'),
            ),
            $this->tweetService->getTweetsPaginated($page, $perPage)
        );
    }
}
