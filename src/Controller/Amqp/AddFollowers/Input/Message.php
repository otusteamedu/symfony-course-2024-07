<?php

namespace App\Controller\Amqp\AddFollowers\Input;

use Symfony\Component\Validator\Constraints as Assert;

class Message
{
    public function __construct(
        #[Assert\Type('numeric')]
        public readonly int $userId,
        #[Assert\Type('string')]
        #[Assert\Length(max: 32)]
        public readonly string $followerLogin,
        #[Assert\Type('numeric')]
        public readonly int $count,
    ) {
    }
}
