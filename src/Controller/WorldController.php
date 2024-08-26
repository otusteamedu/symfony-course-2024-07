<?php

namespace App\Controller;

use App\Domain\Service\UserService;
use App\Domain\Service\UserBuilderService;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class WorldController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService,
        private readonly UserBuilderService $userBuilderService,
    ) {
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function hello(): Response
    {
        $user = $this->userBuilderService->createUserWithTweets(
            'Charles Dickens',
            ['Oliver Twist', 'The Christmas Carol']
        );
        $userData = $this->userService->findUserWithTweetsWithDBALQueryBuilder($user->getId());

        return $this->json($userData);
    }
}
