<?php

namespace App\Infrastructure\Repository;

use App\Domain\Entity\EmailNotification;

class EmailNotificationRepository extends AbstractRepository
{
    public function create(EmailNotification $notification): int
    {
        return $this->store($notification);
    }
}
