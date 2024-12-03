<?php

namespace App\Application\Doctrine\Types;

use App\Domain\ValueObject\AbstractValueObjectString;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

abstract class AbstractStringType extends Type
{
    abstract protected function getConcreteValueObjectType(): string;

    public function convertToPHPValue($value, AbstractPlatform $platform): ?AbstractValueObjectString
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            /** @var AbstractValueObjectString $concreteValueObjectType */
            $concreteValueObjectType = $this->getConcreteValueObjectType();

            return $concreteValueObjectType::fromString($value);
        }

        throw new ConversionException("Could not convert database value $value to {$this->getConcreteValueObjectType()}");
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof AbstractValueObjectString) {
            return $value->getValue();
        }

        throw new ConversionException("Could not convert PHP value $value to ".static::class);
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }
}
