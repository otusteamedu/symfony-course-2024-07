<?php

namespace App\Domain\Service;

use App\Domain\Entity\EmailNotification;
use App\Infrastructure\Repository\EmailNotificationRepository;

class EmailNotificationService
{
    public function __construct(private readonly EmailNotificationRepository $emailNotificationRepository)
    {
    }

    public function saveEmailNotification(string $email, string $text): void {
        $emailNotification = new EmailNotification();
        $emailNotification->setEmail($email);
        $emailNotification->setText($text);
        $this->emailNotificationRepository->create($emailNotification);
    }
}
