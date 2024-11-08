<?php

namespace App\Controller\Web\GetFeed\v1;

use App\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class Controller
{
    public function __construct(private readonly Manager $manager) {
    }

    #[OA\Get(
        operationId: 'v1GetFeed',
        description: 'Получение ленты для пользователя',
        tags: ['feed'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Идентификатор пользователя',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
            new OA\Parameter(
                name: 'count',
                description: 'Количество сообщений в ленте',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный ответ',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'tweets',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(
                                        property: 'id',
                                        type: 'integer',
                                    ),
                                    new OA\Property(
                                        property: 'author',
                                        type: 'string',
                                    ),
                                    new OA\Property(
                                        property: 'text',
                                        type: 'string',
                                    ),
                                    new OA\Property(
                                        property: 'createdAt',
                                        type: 'string',
                                        pattern: '\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}',
                                    ),
                                ],
                                type: 'object'
                            ),
                        ),
                    ],
                    type: 'object'
                ),
            ),
            new OA\Response(
                response: 400,
                description: 'Ошибка валидации',
            ),
        ],
    )]
    #[Route(path: 'api/v1/get-feed/{id}', methods: ['GET'])]
    public function __invoke(#[MapEntity(id: 'id')]User $user, #[MapQueryParameter]?int $count = null): Response
    {
        return new JsonResponse(['tweets' => $this->manager->getFeed($user, $count)]);
    }
}
