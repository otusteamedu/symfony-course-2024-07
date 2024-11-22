<?php

namespace FeedBundle\Controller\Web\GetFeed\v1;

use FeedBundle\Controller\Web\GetFeed\v1\Output\Response as GetFeedResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
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
        operationId: 'v1ServerApiGetFeed',
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
                content: new Model(type: GetFeedResponse::class),
            ),
            new OA\Response(
                response: 400,
                description: 'Ошибка валидации',
            ),
        ],
    )]
    #[Route(path: 'server-api/v1/get-feed/{id}', methods: ['GET'])]
    public function __invoke(int $id, #[MapQueryParameter]?int $count = null): Response
    {
        return new JsonResponse($this->manager->getFeed($id, $count));
    }
}
