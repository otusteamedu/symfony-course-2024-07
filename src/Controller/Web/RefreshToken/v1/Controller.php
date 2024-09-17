<?php

namespace App\Controller\Web\RefreshToken\v1;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class Controller extends AbstractController
{
    public function __construct(private readonly Manager $manager) {
    }

    #[Route(path: 'api/v1/refresh-token', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        return new JsonResponse(['token' => $this->manager->refreshToken($this->getUser())]);
    }
}
