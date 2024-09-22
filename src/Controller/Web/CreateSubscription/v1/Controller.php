<?php

namespace App\Controller\Web\CreateSubscription\v1;

use App\Domain\Entity\User;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class Controller
{
    public function __construct(
        private readonly Manager $manager,
    ) {
    }

    #[Route(
        path: 'api/v1/create-subscription/{author}/{follower}',
        requirements: ['author' => '\d+', 'follower' => '\d+'],
        methods: ['POST'])
    ]
    public function __invoke(#[MapEntity(id: 'author')] User $author, #[MapEntity(id: 'follower')] User $follower): Response
    {
        $this->manager->create($author, $follower);

        return new JsonResponse(null, Response::HTTP_CREATED);
    }
}
