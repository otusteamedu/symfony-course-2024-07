<?php

namespace App\Domain\Event;

class CreateUserEvent
{
    public ?int $id = null;

    public function __construct(
        public readonly string $login,
        public readonly ?string $phone = null,
        public readonly ?string $email = null,
    ) {
    }
}
