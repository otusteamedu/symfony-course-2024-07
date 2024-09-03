<?php

namespace App\Controller\Web\UpdateUserLogin\v1;

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
    public function __construct(private readonly Manager $manager) {
    }

    #[Route(path: 'api/v1/user/{id}', methods: ['PATCH'])]
    public function __invoke(#[MapEntity(expr: 'repository.find(id)')] User $user, Request $request): Response
    {
        $login = $request->query->get('login');
        $this->manager->updateLogin($user, $login);

        return new JsonResponse(['success' => true]);
    }
}
