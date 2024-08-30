<?php

namespace App\Domain\ValueObject;

enum CommunicationChannelEnum: string
{
    case Email = 'email';
    case Phone = 'phone';
}
