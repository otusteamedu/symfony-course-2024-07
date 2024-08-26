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
        $user = $this->userService->create('William Shakespeare');
        $this->userService->removeById($user->getId());
        $usersByLogin = $this->userService->findUsersByLogin($user->getLogin());

        return $this->json(['users' => array_map(static fn (User $user) => $user->toArray(), $usersByLogin)]);
    }
}
