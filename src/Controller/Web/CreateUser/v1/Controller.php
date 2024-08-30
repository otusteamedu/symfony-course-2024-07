<?php

namespace App\Controller\Web\CreateUser\v1;

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

    #[Route(path: 'api/v1/user', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $login = $request->request->get('login');
        $phone = $request->request->get('phone');
        $email = $request->request->get('email');
        $user = $this->manager->create($login, $phone, $email);
        if ($user === null) {
            return new JsonResponse(null, Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($user->toArray());
    }
}
