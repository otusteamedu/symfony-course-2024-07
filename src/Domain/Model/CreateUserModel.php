<?php

namespace App\Domain\Model;

use App\Domain\ValueObject\CommunicationChannelEnum;

use Symfony\Component\Validator\Constraints as Assert;

class CreateUserModel
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $login,
        #[Assert\NotBlank]
        #[Assert\When(
            expression: "this.communicationChannel.value === 'phone'",
            constraints: [new Assert\Length(max: 20)]
        )]
        public readonly string $communicationMethod,
        public readonly CommunicationChannelEnum $communicationChannel,
        public readonly string $password = 'myPass',
        public readonly int $age = 18,
        public readonly bool $isActive = true,
    ) {
    }
}
