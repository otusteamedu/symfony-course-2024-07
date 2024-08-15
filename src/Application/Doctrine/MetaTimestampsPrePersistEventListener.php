<?php

namespace App\Application\Doctrine;

use App\Domain\Entity\HasMetaTimestampsInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

#[AsDoctrineListener(event: Events::prePersist, connection: 'default')]
class MetaTimestampsPrePersistEventListener
{
    public function prePersist(LifecycleEventArgs $event): void
    {
        $entity = $event->getObject();

        if ($entity instanceof HasMetaTimestampsInterface) {
            $entity->setCreatedAt();
            $entity->setUpdatedAt();
        }
    }
}
