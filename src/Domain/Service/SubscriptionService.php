<?php

namespace App\Domain\Service;

use App\Domain\Entity\Subscription;
use App\Domain\Entity\User;
use App\Infrastructure\Repository\SubscriptionRepository;
use App\Infrastructure\Repository\UserRepository;

class SubscriptionService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
    ) {
    }

    public function addSubscription(User $author, User $follower): void
    {
        $subscription = new Subscription();
        $subscription->setAuthor($author);
        $subscription->setFollower($follower);
        $author->addSubscriptionFollower($subscription);
        $follower->addSubscriptionAuthor($subscription);
        $this->subscriptionRepository->create($subscription);
    }

    /**
     * @return User[]
     */
    public function getFollowers(int $authorId): array
    {
        $subscriptions = $this->getSubscriptionsByAuthorId($authorId);
        $mapper = static function(Subscription $subscription) {
            return $subscription->getFollower();
        };

        return array_map($mapper, $subscriptions);
    }

    /**
     * @return Subscription[]
     */
    private function getSubscriptionsByAuthorId(int $authorId): array
    {
        $author = $this->userRepository->find($authorId);
        if (!($author instanceof User)) {
            return [];
        }

        return $this->subscriptionRepository->findAllByAuthor($author);
    }
}
