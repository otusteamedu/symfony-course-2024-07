<?php

namespace App\Domain\Bus;

use App\Domain\DTO\UpdateFeedDTO;

interface UpdateFeedBusInterface
{
    public function sendUpdateFeedMessage(UpdateFeedDTO $updateFeedDTO): bool;
}
