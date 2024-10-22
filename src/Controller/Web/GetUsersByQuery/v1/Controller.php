<?php

namespace App\Controller\Web\GetUsersByQuery\v1;

use App\Domain\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class Controller
{
    public function __construct(private readonly Manager $manager) {
    }

    #[Route(path: 'api/v1/get-users-by-query', methods: ['GET'])]
    public function __invoke(#[MapQueryParameter]string $query, #[MapQueryParameter]int $perPage, #[MapQueryParameter]int $page): Response
    {
        return new JsonResponse(
            [
                'users' => array_map(
                    static fn (User $user): array => $user->toArray(),
                    $this->manager->findUsersByQuery($query, $perPage, $page)
                )
            ]
        );
    }
}
