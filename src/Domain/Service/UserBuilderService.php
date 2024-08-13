<?php

namespace App\Domain\Service;

use App\Domain\Entity\User;

class UserBuilderService
{
    public function __construct(
        private readonly TweetService $tweetService,
        private readonly UserService $userService,
    ) {
    }

    /**
     * @param string[] $texts
     */
    public function createUserWithTweets(string $login, array $texts): User
    {
        $user = $this->userService->create($login);
        foreach ($texts as $text) {
            $this->tweetService->postTweet($user, $text);
        }
        $this->userService->refresh($user);

        return $user;
    }
}
