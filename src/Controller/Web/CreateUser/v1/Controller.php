<?php

namespace App\Controller\Web\CreateUser\v1;

use App\Controller\Web\CreateUser\v1\Input\CreateUserDTO;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class Controller
{
    public function __construct(private readonly Manager $manager) {
    }

    #[Route(path: 'api/v1/user', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] CreateUserDTO $createUserDTO): Response
    {
        $user = $this->manager->create($createUserDTO);

        return new JsonResponse($user->toArray());
    }
}
