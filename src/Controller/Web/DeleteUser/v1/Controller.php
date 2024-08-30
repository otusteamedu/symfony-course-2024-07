<?php

namespace App\Controller\Web\DeleteUser\v1;

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

    #[Route(path: 'api/v1/user/{id}', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function __invoke(int $id): Response
    {
        $result = $this->manager->deleteUserById($id);
        if ($result) {
            return new JsonResponse(['success' => true]);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }
}