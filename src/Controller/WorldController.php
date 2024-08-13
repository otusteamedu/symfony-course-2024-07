<?php

namespace App\Controller;

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
        $user = $this->userBuilderService->createUserWithTweets(
            'J.R.R. Tolkien',
            ['The Hobbit', 'The Lord of the Rings']
        );

        return $this->json($user->toArray());
    }
}
