<?php

namespace App\Domain\Bus;

use App\Domain\DTO\SendNotificationDTO;

interface SendNotificationBusInterface
{
    public function sendNotification(SendNotificationDTO $sendNotificationDTO): bool;
}
