<?php

namespace App\Domain\Bus;

use App\Domain\DTO\AddFollowersDTO;

interface AddFollowersBusInterface
{
    public function sendAddFollowersMessage(AddFollowersDTO $addFollowersDTO): bool;
}
