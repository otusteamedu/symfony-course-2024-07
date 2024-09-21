<?php

namespace App\Controller\Web\GetJsonSchema\v1;

use ApiPlatform\JsonSchema\SchemaFactoryInterface;

class Manager
{
    public function __construct(private readonly SchemaFactoryInterface $jsonSchemaFactory)
    {
    }

    public function getJsonSchemaAction(string $resource): array
    {
        $className = 'App\\Domain\\Entity\\'.ucfirst($resource);
        $schema = $this->jsonSchemaFactory->buildSchema($className);

        return json_decode(json_encode($schema), true);
    }
}
