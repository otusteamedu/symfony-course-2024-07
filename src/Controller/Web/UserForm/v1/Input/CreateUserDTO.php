<?php

namespace App\Controller\Web\UserForm\v1\Input;

use Symfony\Component\Validator\Constraints as Assert;

#[Assert\Expression(
    expression: '(this.email === null and this.phone !== null) or (this.phone === null and this.email !== null)',
    message: 'Eiteher email or phone should be provided',
)]
class CreateUserDTO
{
    public function __construct(
        #[Assert\NotBlank]
        public ?string $login = null,
        public ?string $email = null,
        #[Assert\Length(max: 20)]
        public ?string $phone = null,
        #[Assert\NotBlank]
        public ?string $password = '',
        public ?int $age = 18,
        public ?bool $isActive = false,
    ) {
    }
}
