<?php

namespace App\Domain\Service;

use App\Domain\Entity\SmsNotification;
use App\Infrastructure\Repository\SmsNotificationRepository;

class SmsNotificationService
{
    public function __construct(private readonly SmsNotificationRepository $emailNotificationRepository)
    {
    }

    public function saveSmsNotification(string $phone, string $text): void {
        $emailNotification = new SmsNotification();
        $emailNotification->setPhone($phone);
        $emailNotification->setText($text);
        $this->emailNotificationRepository->create($emailNotification);
    }
}
