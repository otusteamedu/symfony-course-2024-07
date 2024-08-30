<?php

namespace App\Controller\Web\GetUser\v1;

use App\Domain\Entity\User;
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

    #[Route(path: 'api/v1/user', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $userId = $request->query->get('id');
        if ($userId === null) {
            return new JsonResponse(array_map(static fn (User $user): array => $user->toArray(), $this->manager->getAllUsers()));
        }
        $user = $this->manager->getUserById($userId);
        if ($user instanceof User) {
            return new JsonResponse($user->toArray());
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }
}
