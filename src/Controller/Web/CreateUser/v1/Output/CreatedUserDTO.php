<?php

namespace App\Controller\Web\CreateUser\v1\Output;

class CreatedUserDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $login,
        public readonly ?string $avatarLink,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly ?string $phone,
        public readonly ?string $email,
    ) {
    }
}
