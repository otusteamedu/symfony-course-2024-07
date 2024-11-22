<?php

namespace FeedBundle\Domain\Model;

use DateTime;

class TweetModel
{
    public function __construct(
        public readonly int $id,
        public readonly string $author,
        public readonly int $authorId,
        public readonly string $text,
        public readonly DateTime $createdAt,
    ) {
    }

    public function toFeed(): array
    {
        return [
            'id' => $this->id,
            'author' => $this->author,
            'text' => $this->text,
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
