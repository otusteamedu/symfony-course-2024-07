<?php

namespace App\Controller;

use App\Domain\Entity\User;
use App\Domain\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class WorldController extends AbstractController
{
    public function __construct(private readonly UserService $userService)
    {
    }

    public function hello(): Response
    {
        /** @var User $user */
        $user = $this->userService->updateUserLoginWithQueryBuilder(1, 'User is updated again');

        return $this->json($user->toArray());
    }
}
