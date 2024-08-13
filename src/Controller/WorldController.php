<?php

namespace App\Controller;

use App\Domain\Entity\User;
use App\Domain\Service\UserBuilderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class WorldController extends AbstractController
{
    public function __construct(private readonly UserBuilderService $userBuilderService)
    {
    }

    public function hello(): Response
    {
        $users = $this->userBuilderService->createUserWithFollower(
            'J.R.R. Tolkien',
            'Ivan Ivanov'
        );

        return $this->json(array_map(static fn(User $user) => $user->toArray(), $users));
    }
}
