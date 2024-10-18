<?php

namespace App\Domain\DTO;

use App\Domain\ValueObject\CommunicationChannelEnum;

class SendNotificationDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly string $text,
        public readonly CommunicationChannelEnum $channel,
    ) {
    }
}
