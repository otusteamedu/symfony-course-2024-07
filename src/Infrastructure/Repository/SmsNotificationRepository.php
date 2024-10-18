<?php

namespace App\Infrastructure\Repository;

use App\Domain\Entity\SmsNotification;

class SmsNotificationRepository extends AbstractRepository
{
    public function create(SmsNotification $notification): int
    {
        return $this->store($notification);
    }
}
