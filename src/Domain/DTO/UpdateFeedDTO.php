<?php

namespace App\Domain\DTO;

use DateTime;

class UpdateFeedDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $author,
        public readonly int $authorId,
        public readonly string $text,
        public readonly DateTime $createdAt,
        public readonly int $followerId,
    ) {

    }
}
