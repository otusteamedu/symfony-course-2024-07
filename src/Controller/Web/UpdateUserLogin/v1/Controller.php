<?php

namespace App\Controller\Web\UpdateUserLogin\v1;

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

    #[Route(path: 'api/v1/user', methods: ['PATCH'])]
    public function __invoke(Request $request): Response
    {
        $userId = $request->query->get('id');
        $login = $request->query->get('login');
        $result = $this->manager->updateUserLogin($userId, $login);

        if ($result) {
            return new JsonResponse(['success' => true]);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }
}
