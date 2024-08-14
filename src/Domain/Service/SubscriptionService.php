<?php

namespace App\Domain\Service;

use App\Domain\Entity\Subscription;
use App\Domain\Entity\User;
use App\Infrastructure\Repository\SubscriptionRepository;

class SubscriptionService
{
    public function __construct(private readonly SubscriptionRepository $subscriptionRepository)
    {
    }

    public function addSubscription(User $author, User $follower): void
    {
        $subscription = new Subscription();
        $subscription->setAuthor($author);
        $subscription->setFollower($follower);
        $subscription->setCreatedAt();
        $subscription->setUpdatedAt();
        $author->addSubscriptionFollower($subscription);
        $follower->addSubscriptionAuthor($subscription);
        $this->subscriptionRepository->create($subscription);
    }
}
