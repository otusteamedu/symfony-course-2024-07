<?php

namespace App\Domain\Model;

use App\Domain\ValueObject\CommunicationChannelEnum;

class CreateUserModel
{
    public function __construct(
        public readonly string $login,
        public readonly string $communicationMethod,
        public readonly CommunicationChannelEnum $communicationChannel,
    ) {
    }
}
