<?php

namespace App\Infrastructure\Bus;

enum AmqpExchangeEnum: string
{
    case AddFollowers = 'add_followers';
}
