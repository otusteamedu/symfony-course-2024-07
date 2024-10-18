<?php

namespace App\Infrastructure\Bus;

use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class RabbitMqBus
{
    /** @var array<string,ProducerInterface> */
    private array $producers;

    public function __construct(private readonly SerializerInterface $serializer)
    {
        $this->producers = [];
    }

    public function registerProducer(AmqpExchangeEnum $exchange, ProducerInterface $producer): void
    {
        $this->producers[$exchange->value] = $producer;
    }

    public function publishToExchange(AmqpExchangeEnum $exchange, $message, ?string $routingKey = null, ?array $additionalProperties = null): bool
    {
        $serializedMessage = $this->serializer->serialize($message, 'json', [AbstractObjectNormalizer::SKIP_NULL_VALUES => true]);
        if (isset($this->producers[$exchange->value])) {
            $this->producers[$exchange->value]->publish($serializedMessage, $routingKey ?? '', $additionalProperties ?? []);

            return true;
        }

        return false;
    }

    public function publishMultipleToExchange(AmqpExchangeEnum $exchange, array $messages, ?string $routingKey = null, ?array $additionalProperties = null): bool
    {
        $sentCount = 0;
        foreach ($messages as $message) {
            if ($this->publishToExchange($exchange, $message, $routingKey, $additionalProperties)) {
                $sentCount++;
            }
        }

        return $sentCount;
    }
}
