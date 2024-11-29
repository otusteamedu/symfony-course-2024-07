<?php

namespace FeedBundle\Domain\DTO;

class SendNotificationAsyncDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly string $text,
        public readonly string $channel,
    ) {
    }
}
