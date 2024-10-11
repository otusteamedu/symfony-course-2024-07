<?php

namespace App\Controller\Web\PostTweet\v1;

use App\Controller\Exception\AccessDeniedException;
use App\Controller\Web\PostTweet\v1\Input\PostTweetDTO;
use App\Domain\Service\TweetService;
use App\Domain\Service\UserService;

class Manager
{
    public function __construct(
        private readonly UserService $userService,
        private readonly TweetService $tweetService,
    ) {
    }

    public function postTweet(PostTweetDTO $tweetDTO): bool
    {
        $user = $this->userService->findUserById($tweetDTO->userId);

        if ($user === null) {
            return false;
        }

        $this->tweetService->postTweet($user, $tweetDTO->text);

        return true;
    }
}
