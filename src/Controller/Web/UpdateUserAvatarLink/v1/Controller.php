<?php

namespace App\Controller\Web\UpdateUserAvatarLink\v1;

use App\Domain\Entity\User;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class Controller
{
    public function __construct(private readonly Manager $manager)
    {
    }

    #[Route(path: '/api/v1/update-user-avatar-link/{id}', methods: ['POST'])]
    public function getUserByLoginAction(#[MapEntity(id: 'id')] User $user, Request $request): Response
    {
        $this->manager->updateUserAvatarLink($user, $request->files->get('image'));

        return new JsonResponse(['user' => $user->toArray()], Response::HTTP_OK);
    }
}
