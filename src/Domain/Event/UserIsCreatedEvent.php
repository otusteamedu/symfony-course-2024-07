<?php

namespace App\Domain\Event;

class UserIsCreatedEvent
{
    public function __construct(
        public readonly int $id,
        public readonly string $login,
    ) {
    }
}
