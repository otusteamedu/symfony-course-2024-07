<?php

namespace App\Controller\Web\GetJsonSchema\v1;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class Controller
{
    public function __construct(private readonly Manager $manager) {
    }

    #[Route(path: 'api/v1/get-json-schema/{resource}', methods: ['GET'])]
    public function __invoke(string $resource): Response
    {
        return new JsonResponse($this->manager->getJsonSchemaAction($resource));
    }
}
