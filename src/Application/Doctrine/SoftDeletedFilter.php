<?php

namespace App\Application\Doctrine;

use App\Domain\Entity\SoftDeletableInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

class SoftDeletedFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias): string
    {
        if (!$targetEntity->reflClass->implementsInterface(SoftDeletableInterface::class)) {
            return '';
        }

        return $targetTableAlias.'.deleted_at IS NULL';
    }
}
