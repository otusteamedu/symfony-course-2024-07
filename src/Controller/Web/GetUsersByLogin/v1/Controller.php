<?php

namespace App\Controller\Web\GetUsersByLogin\v1;

use App\Domain\Entity\User;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class Controller
{
    #[Route(path: '/api/v1/get-user-by-login/{login}', methods: ['GET'])]
    public function getUserByLoginAction(#[MapEntity(mapping: ['login' => 'login'])] User $user): Response
    {
        return new JsonResponse(['user' => $user->toArray()], Response::HTTP_OK);
    }
}
