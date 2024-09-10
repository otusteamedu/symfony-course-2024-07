<?php

namespace App\Controller\Web\RenderUserList\v1;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class Controller extends AbstractController
{
    public function __construct(private readonly Manager $userManager)
    {
    }

    #[Route(path: '/api/v1/get-user-list', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('user-table.twig', ['users' => $this->userManager->getUserListData()]);
    }
}
