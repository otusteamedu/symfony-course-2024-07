<?php

namespace App\Controller\Amqp\UpdateFeed\Input;

use DateTime;
use Symfony\Component\Validator\Constraints as Assert;

class Message
{
    public function __construct(
        #[Assert\Type('numeric')]
        public readonly int $id,
        public readonly string $author,
        #[Assert\Type('numeric')]
        public readonly int $authorId,
        public readonly string $text,
        public readonly DateTime $createdAt,
        #[Assert\Type('numeric')]
        public readonly int $followerId,
    ) {
    }
}
