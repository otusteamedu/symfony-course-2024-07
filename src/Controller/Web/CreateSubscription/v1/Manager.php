<?php

namespace App\Controller\Web\CreateSubscription\v1;

use App\Domain\Entity\User;
use App\Domain\Service\SubscriptionService;

class Manager
{
    public function __construct(private readonly SubscriptionService $subscriptionService)
    {
    }

    public function create(User $author, User $follower): void
    {
        $this->subscriptionService->addSubscription($author, $follower);
    }
}
