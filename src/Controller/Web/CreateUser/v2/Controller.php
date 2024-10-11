<?php

namespace App\Controller\Web\CreateUser\v2;

use App\Controller\Web\CreateUser\v2\Input\CreateUserDTO;
use App\Controller\Web\CreateUser\v2\Output\CreatedUserDTO;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class Controller
{
    public function __construct(
        private readonly ManagerInterface $manager,
    ) {
    }

    #[Route(path: 'api/v2/user', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] CreateUserDTO $createUserDTO): CreatedUserDTO
    {
        return $this->manager->create($createUserDTO);
    }
}
