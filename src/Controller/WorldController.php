<?php

namespace App\Controller;

use App\Domain\Entity\User;
use App\Domain\Service\UserService;
use DateInterval;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class WorldController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService,
    ) {
    }

    public function hello(): Response
    {
        $this->userService->createWithPhone('Phone user', '+1234567890');
        $this->userService->createWithEmail('Email user', 'my@mail.ru');
        $phoneUsers = $this->userService->findUsersByLogin('Phone user');
        $emailUsers = $this->userService->findUsersByLogin('Email user');

        return $this->json(
            ['users' => array_map(static fn (User $user) => $user->toArray(), array_merge($phoneUsers, $emailUsers))]
        );
    }
}
