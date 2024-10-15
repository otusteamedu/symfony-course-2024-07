<?php

namespace App\Domain\Service;

use App\Domain\Entity\User;
use App\Domain\Model\CreateUserModel;
use App\Domain\ValueObject\CommunicationChannelEnum;

class FollowerService
{
    public function __construct(
        private readonly UserService $userService,
        private readonly SubscriptionService $subscriptionService,
    ) {

    }

    public function addFollowers(User $user, string $followerLoginPrefix, int $count): int
    {
        $createdFollowers = 0;
        for ($i = 0; $i < $count; $i++) {
            $login = "{$followerLoginPrefix}_$i";
            $channel = random_int(0, 2) === 1 ? CommunicationChannelEnum::Email : CommunicationChannelEnum::Phone;
            $model = new CreateUserModel(
                $login,
                match ($channel) {
                    CommunicationChannelEnum::Email => "{$login}@mail.ru",
                    CommunicationChannelEnum::Phone => '+'.str_pad((string)abs(crc32($login)), 10, '0'),
                },
                $channel,
                "{$login}_password",
            );
            $follower = $this->userService->create($model);
            $this->subscriptionService->addSubscription($user, $follower);
            $createdFollowers++;
        }

        return $createdFollowers;
    }
}
