<?php

namespace App\Infrastructure\Bus\Adapter;

use App\Domain\Bus\AddFollowersBusInterface;
use App\Domain\DTO\AddFollowersDTO;
use App\Infrastructure\Bus\AmqpExchangeEnum;
use App\Infrastructure\Bus\RabbitMqBus;

class AddFollowersRabbitMqBus implements AddFollowersBusInterface
{
    public function __construct(private readonly RabbitMqBus $rabbitMqBus)
    {
    }

    public function sendAddFollowersMessage(AddFollowersDTO $addFollowersDTO): bool
    {
        return $this->rabbitMqBus->publishToExchange(AmqpExchangeEnum::AddFollowers, $addFollowersDTO);
    }
}
