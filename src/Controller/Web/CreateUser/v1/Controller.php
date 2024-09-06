<?php

namespace App\Controller\Web\CreateUser\v1;

use App\Controller\Web\CreateUser\v1\Input\CreateUserDTO;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\SerializerInterface;

#[AsController]
class Controller
{
    public function __construct(
        private readonly Manager $manager,
        private readonly SerializerInterface $serializer
    ) {
    }

    #[Route(path: 'api/v1/user', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] CreateUserDTO $createUserDTO): Response
    {
        $user = $this->manager->create($createUserDTO);

        return new JsonResponse($this->serializer->serialize($user, JsonEncoder::FORMAT), Response::HTTP_OK, [], true);
    }
}
