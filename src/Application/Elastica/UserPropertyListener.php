<?php

namespace App\Application\Elastica;

use App\Domain\Entity\EmailUser;
use App\Domain\Entity\User;
use App\Domain\ValueObject\CommunicationChannelEnum;
use FOS\ElasticaBundle\Event\PostTransformEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UserPropertyListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            PostTransformEvent::class => 'addCommunicationMethodProperties'
        ];
    }

    public function addCommunicationMethodProperties(PostTransformEvent $event): void
    {
        $user = $event->getObject();
        if ($user instanceof User) {
            $document = $event->getDocument();
            $document->set(
                'communicationMethod',
                $user instanceof EmailUser ? CommunicationChannelEnum::Email : CommunicationChannelEnum::Phone
            );
        }
    }
}
