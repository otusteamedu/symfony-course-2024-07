<?php

namespace App\Controller\Web\AddFollowers\v1;

use App\Controller\Web\AddFollowers\v1\Input\AddFollowersDTO;
use App\Domain\Entity\User;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class Controller
{
    public function __construct(
        private readonly Manager $manager,
    ) {
    }

    #[Route(path: 'api/v1/add-followers/{id}', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function __invoke(#[MapEntity(id: 'id')] User $author, #[MapRequestPayload] AddFollowersDTO $addFollowersDTO): Response
    {
        return new JsonResponse(['count' => $this->manager->addFollowers($author, $addFollowersDTO)]);
    }
}
