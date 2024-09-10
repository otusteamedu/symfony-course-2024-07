<?php

namespace App\Controller\Web\PhoneUserForm\v1;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CreateController extends AbstractController
{
    public function __construct(private readonly Manager $manager)
    {
    }

    #[Route(path: '/api/v1/create-phone-user', methods: ['GET', 'POST'])]
    public function manageUserAction(Request $request): Response
    {
        return $this->render('phone-user.html.twig', $this->manager->getFormData($request));
    }
}
