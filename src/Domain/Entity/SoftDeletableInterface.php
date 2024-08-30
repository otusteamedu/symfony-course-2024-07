<?php

namespace App\Domain\Entity;

use DateTime;

interface SoftDeletableInterface
{
    public function getDeletedAt(): ?DateTime;

    public function setDeletedAt(): void;
}
