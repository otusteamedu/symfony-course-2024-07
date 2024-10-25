<?php

namespace App\Application\Doctrine;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class UserRepository extends EntityRepository
{
    public function createIsActiveQueryBuilder(string $alias): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->andWhere("$alias.isActive = :isActive")
            ->setParameter('isActive', true);
    }
}
