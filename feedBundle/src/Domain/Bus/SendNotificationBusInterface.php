<?php

namespace FeedBundle\Domain\Bus;

use FeedBundle\Domain\DTO\SendNotificationDTO;

interface SendNotificationBusInterface
{
    public function sendNotification(SendNotificationDTO $sendNotificationDTO): bool;
}
