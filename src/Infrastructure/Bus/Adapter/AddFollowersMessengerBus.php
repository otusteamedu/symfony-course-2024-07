<?php

namespace App\Infrastructure\Bus\Adapter;

use App\Domain\Bus\AddFollowersBusInterface;
use App\Domain\DTO\AddFollowersDTO;
use Symfony\Component\Messenger\MessageBusInterface;

class AddFollowersMessengerBus implements AddFollowersBusInterface
{
    public function __construct(private readonly MessageBusInterface $messageBus)
    {
    }

    public function sendAddFollowersMessage(AddFollowersDTO $addFollowersDTO): bool
    {
        for ($i = 0; $i < $addFollowersDTO->count; $i++) {
            $this->messageBus->dispatch(new AddFollowersDTO($addFollowersDTO->userId, $addFollowersDTO->followerLogin."_$i", 1));
        }

        return true;
    }
}
