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

        return $user;
    }

    /**
     * @return User[]
     */
    public function createUserWithFollower(string $login, string $followerLogin): array
    {
        $user = $this->userService->create($login);
        $follower = $this->userService->create($followerLogin);
        $this->userService->subscribeUser($user, $follower);

        return [$user, $follower];
    }
}
