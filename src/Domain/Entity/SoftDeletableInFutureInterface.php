<?php

namespace App\Domain\Entity;

use DateInterval;

interface SoftDeletableInFutureInterface
{
    public function setDeletedAtInFuture(DateInterval $dateInterval): void;
}
