<?php

namespace App\Controller;

use App\Domain\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class WorldController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService,
    )
    {
    }

    public function hello(): Response
    {
        $user = $this->userService->create('My user');

        return $this->json($user->toArray());
    }
}
