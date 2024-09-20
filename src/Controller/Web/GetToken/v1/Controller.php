<?php

namespace App\Controller\Web\GetToken\v1;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class Controller
{
    public function __construct(private readonly Manager $manager) {
    }

    #[Route(path: 'api/v1/get-token', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        return new JsonResponse(['token' => $this->manager->getToken($request)]);
    }
}
