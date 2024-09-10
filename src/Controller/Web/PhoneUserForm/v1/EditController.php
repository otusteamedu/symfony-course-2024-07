<?php

namespace App\Controller\Web\PhoneUserForm\v1;

use App\Domain\Entity\User;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EditController extends AbstractController
{
    public function __construct(private readonly Manager $manager)
    {
    }

    #[Route(path: '/api/v1/update-phone-user/{id}', methods: ['GET', 'PATCH'])]
    public function manageUserAction(Request $request, #[MapEntity(id: 'id')] User $user): Response
    {
        return $this->render('phone-user.html.twig', $this->manager->getFormData($request, $user));
    }
}
