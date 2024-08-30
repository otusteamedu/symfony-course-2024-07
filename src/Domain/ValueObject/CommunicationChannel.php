<?php

namespace App\Domain\ValueObject;

use RuntimeException;

class CommunicationChannel
{
    private const EMAIL = 'email';
    private const PHONE = 'phone';
    private const ALLOWED_VALUES = [self::PHONE, self::EMAIL];

    private function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (!in_array($value, self::ALLOWED_VALUES, true)) {
            throw new RuntimeException('Invalid communication channel value');
        }

        return new self($value);
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
