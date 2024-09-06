<?php

namespace App\Controller\Web\CreateUser\v1\Input;

use Symfony\Component\Validator\Constraints as Assert;

class CreateUserDTO
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $login,
        public readonly ?string $email,
        public readonly ?string $phone,
    ) {

    }
}
