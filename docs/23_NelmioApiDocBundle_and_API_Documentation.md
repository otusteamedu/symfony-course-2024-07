# NelmioApiDocBundle и документация API

Запускаем контейнеры командой `docker-compose up -d`

## Установка NelmioApiDocBundle

1. Заходим в контейнер командой `docker exec -it php sh`. Дальнейшие команды выполняем из контейнера
2. Устанавливаем пакет `nelmio/api-doc-bundle`
3. Заходим по адресу `http://localhost:7777/api/doc.json`, видим JSON-описание нашего API
4. Заходим по адресу `http://localhost:7777/api/doc`, видим ошибку

## Добавляем роутинг на UI

1. Добавляем в файл `config/routes.yaml`
    ```yaml
    app.swagger_ui:
      path: /api/doc
      methods: GET
      defaults: { _controller: nelmio_api_doc.controller.swagger_ui }
    ```
2. Ещё раз заходим по адресу `http://localhost:7777/api/doc`, видим описание API

## Добавляем авторизацию

1. В файле `config/packages/security.yaml` в секцию `access_control` добавляем строку
    ```
    - { path: ^/api/doc, roles: ROLE_ADMIN }
    ```
2. Ещё раз заходим по адресу `http://localhost:7777/api/doc`, видим требование авторизоваться

## Выделяем зону

1. Исправляем в файле `config/packages/nelmio_api_doc.yaml` секцию `areas`
    ```yaml
    feed:
      path_patterns:
        - ^/api/v1/get-feed
    default:
      path_patterns:
        - ^/api(?!/doc?$)
    ```
2. В файл `config/routes.yaml` добавляем
    ```yaml
    app.swagger_ui_areas:
      path: /api/doc/{area}
      methods: GET
      defaults: { _controller: nelmio_api_doc.controller.swagger_ui }
    ```
3. Заходим по адресу `http://localhost:7777/api/doc/feed`, видим выделенный endpoint и все подмешиваемые API Platform
   endpoint'ы

## Добавляем декоратор для отключения лишних endpoint'ов

1. Добавляем класс `App\Application\OpenApi\ApiPlatformFactoryDecorator`
    ```php
    <?php
    
    namespace App\Application\OpenApi;
    
    use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
    use ApiPlatform\OpenApi\Model\Components;
    use ApiPlatform\OpenApi\Model\Paths;
    use ApiPlatform\OpenApi\OpenApi;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\RequestStack;
    
    class ApiPlatformFactoryDecorator implements OpenApiFactoryInterface
    {
        public const FEED_AREA_NAME = 'feed';
    
        public function __construct(
            private readonly OpenApiFactoryInterface $decoratedFactory,
            private readonly RequestStack $requestStack,
        ) {
        }
    
        public function __invoke(array $context = []): OpenApi
        {
            $area = $this->getAreaName();
            $openApi = $this->decoratedFactory->__invoke($context);
            if ($area !== self::FEED_AREA_NAME) {
                return $openApi;
            }
    
            return $openApi->withPaths(new Paths())->withComponents(new Components());
        }
    
        private function getAreaName(): ?string
        {
            $request = $this->requestStack->getCurrentRequest();
            if ($request === null) {
                return $this->getAreaNameFromConsole();
            }
    
            return $this->getAreaNameFromRequest($request);
        }
    
        private function getAreaNameFromConsole(): ?string
        {
            $request = Request::createFromGlobals();
            foreach ($request->server->get('argv') as $arg) {
                $matches = [];
                preg_match('/^--area=(?<area>.+)/', (string) $arg, $matches);
                if (isset($matches['area'])) {
                    return $matches['area'];
                }
            }
    
            return null;
        }
    
        private function getAreaNameFromRequest(Request $request): ?string
        {
            $pathInfo = $request->getPathInfo();
            $matches = [];
            preg_match('/^\/api\/doc\/(?<area>[^\/.]+)/', $pathInfo, $matches);
            return $matches['area'] ?? null;
        }
    }
    ```
2. Исправляем файл `config/packages/nelmio_api_doc.yaml`
    ```yaml
    nelmio_api_doc:
        documentation:
            info:
                title: My App
                description: This is an awesome app!
                version: 1.0.0
        areas: # to filter documented areas
            !php/const App\Application\OpenApi\ApiPlatformFactoryDecorator::FEED_AREA_NAME:
                path_patterns:
                    - ^/api/v1/get-feed
            default:
                path_patterns:
                    - ^/api(?!/doc$) # Accepts routes under /api except /api/doc    
    ```
3. В файле `config/services.yaml` добавляем описание нового сервиса
    ```yaml
    App\Application\OpenApi\ApiPlatformFactoryDecorator:
        decorates: 'api_platform.openapi.factory'
        arguments:
            - '@.inner'
        autoconfigure: false
    ```
4. Заходим по адресу `http://localhost:7777/api/doc/feed`, видим только выделенный endpoint
5. Заходим по адресу `http://localhost:7777/api/doc`, видим полный список endpoint'ов, включая подмешанные API Platform
 
## Описываем модель в контроллере

1. Исправляем класс `App\Controller\Web\GetFeed\v1\Controller`
    ```php
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
    ```
2. Заходим по адресу `http://localhost:7777/api/doc`, видим, что endpoint выделен в отдельный тэг и обновлённое
   описание параметров и ответа

## Используем DTO

1. Добавляем класс `App\Controller\Web\GetFeed\v1\Output\TweetDTO`
    ```php
    <?php
    
    namespace App\Controller\Web\GetFeed\v1\Output;
    
    use OpenApi\Attributes as OA;
    
    class TweetDTO
    {
        public function __construct(
            #[OA\Property(type: 'integer')]
            public int $id,
            #[OA\Property(type: 'string')]
            public string $author,
            #[OA\Property(type: 'string')]
            public string $text,
            #[OA\Property(type: 'string', format: '\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}')]
            public string $createdAt,
        ) {
        }
    }
    ```
2. Добавляем класс `App\Controller\Web\GetFeed\v1\Output\Response`
    ```php
    <?php
    
    namespace App\Controller\Web\GetFeed\v1\Output;
    
    use Nelmio\ApiDocBundle\Attribute\Model;
    use OpenApi\Attributes as OA;
    
    class Response
    {
        /**
         * @param TweetDTO[] $tweets
         */
        public function __construct(
            #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: TweetDTO::class)))]
            public array $tweets,
        ) {
        }
    }
    ```
3. Исправляем класс `App\Controller\Web\GetFeed\v1\Manager`
    ```php
    <?php
    
    namespace App\Controller\Web\GetFeed\v1;
    
    use App\Controller\Web\GetFeed\v1\Output\Response;
    use App\Controller\Web\GetFeed\v1\Output\TweetDTO;
    use App\Domain\Entity\User;
    use App\Domain\Service\FeedService;
    
    class Manager
    {
        private const DEFAULT_FEED_SIZE = 20;
    
        public function __construct(private readonly FeedService $feedService)
        {
        }
    
        public function getFeed(User $user, ?int $count = null): Response
        {
            return new Response(
                array_map(
                    static fn (array $tweetData): TweetDTO => new TweetDTO(...$tweetData),
                    $this->feedService->ensureFeed($user, $count ?? self::DEFAULT_FEED_SIZE),
                )
            );
        }
    }
    ```
4. Исправляем класс `App\Controller\Web\GetFeed\v1\Controller`
    ```php
    <?php
    
    namespace App\Controller\Web\GetFeed\v1;
    
    use App\Domain\Entity\User;
    use App\Controller\Web\GetFeed\v1\Output\Response as GetFeedResponse;
    use Nelmio\ApiDocBundle\Attribute\Model;
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
                    content: new Model(type: GetFeedResponse::class),
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
            return new JsonResponse($this->manager->getFeed($user, $count));
        }
    }
    ```
