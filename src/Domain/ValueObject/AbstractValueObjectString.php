<?php

namespace App\Domain\ValueObject;

use JsonSerializable;

abstract class AbstractValueObjectString implements ValueObjectInterface, JsonSerializable
{
    private readonly string $value;

    final public function __construct(string $value)
    {
        $this->validate($value);

        $this->value = $this->transform($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function fromString(string $value): static
    {
        return new static($value);
    }

    public function equals(ValueObjectInterface $other): bool
    {
        return get_class($this) === get_class($other) && $this->getValue() === $other->getValue();
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    protected function validate(string $value): void
    {
    }

    protected function transform(string $value): string
    {
        return $value;
    }
}
