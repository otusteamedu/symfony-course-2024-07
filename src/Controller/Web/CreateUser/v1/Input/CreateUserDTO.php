<?php

namespace App\Controller\Web\CreateUser\v1\Input;

use Symfony\Component\Validator\Constraints as Assert;

#[Assert\Expression(
    expression: '(this.email === null and this.phone !== null) or (this.phone === null and this.email !== null)',
    message: 'Eiteher email or phone should be provided',
)]
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
