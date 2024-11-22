<?php

namespace FeedBundle\Domain\DTO;

class SendNotificationDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly string $text,
        public readonly string $channel,
    ) {
    }
}
