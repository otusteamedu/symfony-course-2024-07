<?php

namespace App\Infrastructure\Repository;

use App\Domain\Entity\Subscription;

/**
 * @extends AbstractRepository<Subscription>
 */
class SubscriptionRepository extends AbstractRepository
{
    public function create(Subscription $subscription): int
    {
        return $this->store($subscription);
    }
}
