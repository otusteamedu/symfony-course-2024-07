<?php

namespace App\Domain\ValueObject;

interface ValueObjectInterface
{
    public function equals(ValueObjectInterface $other): bool;

    public function getValue(): mixed;
}
