<?php

namespace App\Infrastructure\Repository;

use App\Domain\Entity\Subscription;
use App\Domain\Entity\User;

/**
 * @extends AbstractRepository<Subscription>
 */
class SubscriptionRepository extends AbstractRepository
{
    public function create(Subscription $subscription): int
    {
        return $this->store($subscription);
    }

    /**
     * @return Subscription[]
     */
    public function findAllByAuthor(User $author): array
    {
        $subscriptionRepository = $this->entityManager->getRepository(Subscription::class);
        return $subscriptionRepository->findBy(['author' => $author]) ?? [];
    }
}
