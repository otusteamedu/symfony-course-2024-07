# Кэширование

## Memcached в качестве кэша Doctrine

### Устанавливаем Memcached

1. Добавляем в файл `docker/Dockerfile`
    1. Установку пакета `libmemcached-dev` через `apk`
    2. Установку расширения `memcached` через `pecl`
    3. Включение расширения командой `echo "extension=memcached.so" > /usr/local/etc/php/conf.d/memcached.ini`
2. Добавляем сервис Memcached в `docker-compose.yml`
    ```yaml
    memcached:
        image: memcached:latest
        container_name: 'memcached'
        restart: always
        ports:
           - 11211:11211
    ```
3. В файл `.env` добавляем
    ```shell
    MEMCACHED_DSN=memcached://memcached:11211
    ```
4. Пересобираем и запускаем контейнеры командой `docker-compose up -d --build`
5. Подключаемся к Memcached командой `telnet 127.0.0.1 11211` и проверяем, что он пустой (команда `stats items`)

### Добавляем контроллер для получения твитов

1. Выполняем запрос Add user v2 из Postman-коллекции v7
2. Добавим в БД 10 тысяч случайных твитов запросом
    ```sql
    INSERT INTO tweet (created_at, updated_at, author_id, text)
    SELECT NOW(), NOW(), 1, md5(random()::TEXT) FROM generate_series(1,10000);
    ```
3. В классе `App\Infrastructure\Repository\TweetRepository` добавляем метод `getTweetsPaginated`
    ```php
    /**
     * @return Tweet[]
     */
    public function getTweetsPaginated(int $page, int $perPage): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('t')
            ->from(Tweet::class, 't')
            ->orderBy('t.id', 'DESC')
            ->setFirstResult($perPage * $page)
            ->setMaxResults($perPage);

        return $qb->getQuery()->getResult();
    }
    ```
4. В класс `App\Domain\Service\TweetService` добавляем метод `getTweetsPaginated`
    ```php
    /**
     * @return Tweet[]
     */
    public function getTweetsPaginated(int $page, int $perPage): array
    {
        return $this->tweetRepository->getTweetsPaginated($page, $perPage);
    }
    ```
5. Добавляем класс `App\Controller\Web\GetTweet\v1\Output\TweetDTO`
    ```php
    <?php
    
    namespace App\Controller\Web\GetTweet\v1\Output;
    
    class TweetDTO
    {
        public function __construct(
            public readonly int $id,
            public readonly string $text,
            public readonly string $author,
            public readonly string $createdAt 
        ) {
        }
    }
    ```
6. Добавляем класс `App\Controller\Web\GetTweet\v1\Manager`
    ```php
    <?php
    
    namespace App\Controller\Web\GetTweet\v1;
    
    use App\Controller\Web\GetTweet\v1\Output\TweetDTO;
    use App\Domain\Entity\Tweet;
    use App\Domain\Service\TweetService;
    
    class Manager
    {
        public function __construct(private readonly TweetService $tweetService)
        {
        }
    
        /**
         * @return Tweet[]
         */
        public function getTweetsPaginated(int $page, int $perPage): array
        {
            return array_map(
                static fn (Tweet $tweet) => new TweetDTO(
                    $tweet->getId(),
                    $tweet->getText(),
                    $tweet->getAuthor()->getLogin(),
                    $tweet->getCreatedAt()->format('Y-m-d H:i:s'),
                ),
                $this->tweetService->getTweetsPaginated($page, $perPage)
            );
        }
    }
    ```
7. Добавляем класс `App\Controller\Web\GetTweet\v1\Controller`
    ```php
    <?php
    
    namespace App\Controller\Web\GetTweet\v1;
    
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
    
        #[Route(path: 'api/v1/get-tweet', methods: ['GET'])]
        public function __invoke(#[MapQueryParameter]int $page, #[MapQueryParameter]int $perPage): Response
        {
            return new JsonResponse(['tweets' => $this->manager->getTweetsPaginated($page, $perPage)]);
        }
    }
    ```
8. Выполняем запрос Get tweet из Postman-коллекции v7, видим, что результат возвращается

### Включаем кэширование в Doctrine

1. В файле `config/packages/doctrine.yaml`:
    1. Добавляем в секцию `orm`
        ```yaml
        metadata_cache_driver:
            type: pool
            pool: doctrine.system_cache_pool
        query_cache_driver:
            type: pool
            pool: doctrine.system_cache_pool
        result_cache_driver:
            type: pool
            pool: doctrine.result_cache_pool
        ```
    2. Добавляем секцию `services`
        ```yaml
        services:
            doctrine_memcached_provider:
                class: Memcached
                factory: Symfony\Component\Cache\Adapter\MemcachedAdapter::createConnection
                arguments:
                    - '%env(MEMCACHED_DSN)%'
                    - PREFIX_KEY: 'my_app_doctrine'
        ```
    3. Добавляем секцию `framework`
        ```yaml
        framework:
            cache:
                pools:
                    doctrine.result_cache_pool:
                        adapter: cache.adapter.memcached
                        provider: doctrine_memcached_provider
                    doctrine.system_cache_pool:
                        adapter: cache.adapter.memcached
                        provider: doctrine_memcached_provider
        ```
2. Выполняем запрос Get Tweet из Postman-коллекции v7 для прогрева кэша
3. Проверяем, что кэш прогрелся
    1. В Memcached выполняем `stats items`, видим там запись (или две записи)
    2. Выводим каждую запись командой `stats cachedump K 1000`, где K - идентификатор записи
    3. Получаем содержимое ключей командой `get KEY`, где `KEY` - ключ из записи
    4. Удостоверяемся, что это query и metadata кэши

### Добавляем кэширование результата запроса

1. Включаем result cache в класс `App\Infrastructure\Repository\TweetRepository` в методе `getTweetsPaginated` в
   последней строке
    ```php
    return $qb->getQuery()->enableResultCache(null, "tweets_{$page}_$perPage")->getResult();
    ```
2. Выполняем запрос Get tweet из Postman-коллекции v7 для прогрева кэша
3. В Memcached находим ключ с суффиксом tweets_PAGE_PER_PAGE, где `PAGE` и `PER_PAGE` - значения одноимённых параметров
   запроса, и выполняем для него команду `get`, видим содержимое result cache

## Redis в качестве кэша на уровне приложения

### Подключаем redis

1. Добавляем сервис Memcached в `docker-compose.yml`
    ```yaml
    redis:
        container_name: 'redis'
        image: redis:alpine
        ports:
          - 6379:6379
    ```
2. Для включения кэша на уровне приложения в файле `config/packages/cache.yaml` добавляем в секцию `cache`
    ```yaml
    app: cache.adapter.redis
    default_redis_provider: '%env(REDIS_DSN)%'
    ```
3. В файл `.env` добавляем
    ```shell
    REDIS_DSN=redis://redis:6379
    ```
4. Запускаем новые контейнеры командой `docker-compose up -d`
5. Подключаемся к Redis командой `telnet 127.0.0.1 6379`
6. Выполняем `keys *`, видим, что кэш пустой

### Подключаем кэш на уровне приложения

1. Заходим в контейнер командой `docker exec -it php sh`
2. Обновляем пакет `symfony/cache` (минимальная подходящая нам версия 7.1.4)
3. Добавляем класс `App\Domain\Model\TweetModel`
    ```php
    <?php
    
    namespace App\Domain\Model;
    
    use DateTime;
    
    class TweetModel
    {
        public function __construct(
            public readonly int $id,
            public readonly string $author,
            public readonly string $text,
            public readonly DateTime $createdAt,
        ) {
        }
    }
    ``` 
4. Добавляем интерфейс `App\Domain\Repository\TweetRepositoryInterface`
    ```php
    <?php
    
    namespace App\Domain\Repository;
    
    use App\Domain\Entity\Tweet;
    use App\Domain\Model\TweetModel;
    
    interface TweetRepositoryInterface
    {
        public function create(Tweet $tweet): int;
    
        /**
         * @return TweetModel[]
         */
        public function getTweetsPaginated(int $page, int $perPage): array;
    }
    ```
5. Добавляем класс `App\Infrastructure\Repository\TweetRepositoryCacheDecorator`
    ```php
    <?php
    
    namespace App\Infrastructure\Repository;
    
    use App\Domain\Entity\Tweet;
    use App\Domain\Model\TweetModel;
    use App\Domain\Repository\TweetRepositoryInterface;
    use Psr\Cache\CacheItemPoolInterface;
    use Psr\Cache\InvalidArgumentException;
    
    class TweetRepositoryCacheDecorator implements TweetRepositoryInterface
    {
        public function __construct(
            private readonly TweetRepository $tweetRepository,
            private readonly CacheItemPoolInterface $cacheItemPool,
        ) {
        }
    
        public function create(Tweet $tweet): int
        {
            return $this->tweetRepository->create($tweet);
        }
    
        /**
         * @return TweetModel[]
         * @throws InvalidArgumentException
         */
        public function getTweetsPaginated(int $page, int $perPage): array
        {
            $tweetsItem = $this->cacheItemPool->getItem($this->getCacheKey($page, $perPage));
            if (!$tweetsItem->isHit()) {
                $tweets = $this->tweetRepository->getTweetsPaginated($page, $perPage);
                $tweetsItem->set(
                    array_map(
                        static fn (Tweet $tweet): TweetModel => new TweetModel(
                            $tweet->getId(),
                            $tweet->getAuthor()->getLogin(),
                            $tweet->getText(),
                            $tweet->getCreatedAt(),
                        ),
                        $tweets
                    )
                );
                $this->cacheItemPool->save($tweetsItem);
            }
    
            return $tweetsItem->get();
        }
    
        private function getCacheKey(int $page, int $perPage): string
        {
            return "tweets_{$page}_$perPage";
        }
    }
    ```
6. В файле `config/services.yaml` добавляем новый биндинг
    ```yaml
    App\Domain\Repository\TweetRepositoryInterface:
        alias: App\Infrastructure\Repository\TweetRepositoryCacheDecorator
    ```
7. Исправляем класс `App\Domain\Service\TweetService`
    ```php
    <?php
    
    namespace App\Domain\Service;
    
    use App\Domain\Entity\Tweet;
    use App\Domain\Entity\User;
    use App\Domain\Model\TweetModel;
    use App\Domain\Repository\TweetRepositoryInterface;
    
    class TweetService
    {
        public function __construct(private readonly TweetRepositoryInterface $tweetRepository)
        {
        }
    
        public function postTweet(User $author, string $text): void
        {
            $tweet = new Tweet();
            $tweet->setAuthor($author);
            $tweet->setText($text);
            $tweet->setCreatedAt();
            $tweet->setUpdatedAt();
            $author->addTweet($tweet);
            $this->tweetRepository->create($tweet);
        }
    
        /**
         * @return TweetModel[]
         */
        public function getTweetsPaginated(int $page, int $perPage): array
        {
            return $this->tweetRepository->getTweetsPaginated($page, $perPage);
        }
    }
    ```
8. Исправляем класс `App\Controller\Web\GetTweet\v1\Manager`
    ```php
    <?php
    
    namespace App\Controller\Web\GetTweet\v1;
    
    use App\Controller\Web\GetTweet\v1\Output\TweetDTO;
    use App\Domain\Model\TweetModel;
    use App\Domain\Service\TweetService;
    
    class Manager
    {
        public function __construct(private readonly TweetService $tweetService)
        {
        }
    
        /**
         * @return TweetModel[]
         */
        public function getTweetsPaginated(int $page, int $perPage): array
        {
            return array_map(
                static fn (TweetModel $tweet) => new TweetDTO(
                    $tweet->id,
                    $tweet->text,
                    $tweet->author,
                    $tweet->createdAt->format('Y-m-d H:i:s'),
                ),
                $this->tweetService->getTweetsPaginated($page, $perPage)
            );
        }
    }
    ```
9. Выполняем запрос Get tweet из Postman-коллекции v7 для прогрева кэша
10. В Redis ищем ключи от приложения командой `keys *tweets*`
11. Выводим найденный ключ командой `get KEY`, где `KEY` - найденный ключ

### Подсчитываем количество cache hit/miss

1. В классе `App\Infrastructure\Storage\MetricsStorage` добавляем новые константы
    ```php
    public const CACHE_HIT_PREFIX = 'cache.hit.';
    public const CACHE_MISS_PREFIX = 'cache.miss.';
    ```
2. Добавляем класс `App\Application\Symfony\AdapterCountingDecorator`
    ```php
    <?php
    
    namespace App\Application\Symfony;
    
    use App\Infrastructure\Storage\MetricsStorage;
    use Psr\Cache\CacheItemInterface;
    use Psr\Cache\InvalidArgumentException;
    use Psr\Log\LoggerAwareInterface;
    use Psr\Log\LoggerInterface;
    use Symfony\Component\Cache\Adapter\AbstractAdapter;
    use Symfony\Component\Cache\Adapter\AdapterInterface;
    use Symfony\Component\Cache\CacheItem;
    use Symfony\Component\Cache\ResettableInterface;
    use Symfony\Contracts\Cache\CacheInterface;
    
    class AdapterCountingDecorator implements AdapterInterface, CacheInterface, LoggerAwareInterface, ResettableInterface
    {
        public function __construct(
            private readonly AbstractAdapter $adapter,
            private readonly MetricsStorage $metricsStorage,
        )
        {
            $this->adapter->setCallbackWrapper(null);
        }
    
        public function getItem($key): CacheItem
        {
            $result = $this->adapter->getItem($key);
            $this->incCounter($result);
    
            return $result;
        }
    
        /**
         * @param string[] $keys
         *
         * @return iterable
         *
         * @throws InvalidArgumentException
         */
        public function getItems(array $keys = []): array
        {
            $result = $this->adapter->getItems($keys);
            foreach ($result as $item) {
                $this->incCounter($item);
            }
    
            return $result;
        }
    
        public function clear(string $prefix = ''): bool
        {
            return $this->adapter->clear($prefix);
        }
    
        public function get(string $key, callable $callback, float $beta = null, array &$metadata = null): mixed
        {
            return $this->adapter->get($key, $callback, $beta, $metadata);
        }
    
        public function delete(string $key): bool
        {
            return $this->adapter->delete($key);
        }
    
        public function hasItem($key): bool
        {
            return $this->adapter->hasItem($key);
        }
    
        public function deleteItem($key): bool
        {
            return $this->adapter->deleteItem($key);
        }
    
        public function deleteItems(array $keys): bool
        {
            return $this->adapter->deleteItems($keys);
        }
    
        public function save(CacheItemInterface $item): bool
        {
            return $this->adapter->save($item);
        }
    
        public function saveDeferred(CacheItemInterface $item): bool
        {
            return $this->adapter->saveDeferred($item);
        }
    
        public function commit(): bool
        {
            return $this->adapter->commit();
        }
    
        public function setLogger(LoggerInterface $logger): void
        {
            $this->adapter->setLogger($logger);
        }
    
        public function reset(): void
        {
            $this->adapter->reset();
        }
    
        private function incCounter(CacheItemInterface $cacheItem): void
        {
            if ($cacheItem->isHit()) {
                $this->metricsStorage->increment(MetricsStorage::CACHE_HIT_PREFIX.$cacheItem->getKey());
            } else {
                $this->metricsStorage->increment(MetricsStorage::CACHE_MISS_PREFIX.$cacheItem->getKey());
            }
        }
    }
    ```
3. В файле `config/services.yaml` добавляем новые описания сервисов
    ```yaml
    redis_client:
        class: Redis
        factory: Symfony\Component\Cache\Adapter\RedisAdapter::createConnection
        arguments:
            - '%env(REDIS_DSN)%'

    redis_adapter:
        class: Symfony\Component\Cache\Adapter\RedisAdapter
        arguments:
            - '@redis_client'
            - 'my_app'

    App\Application\Symfony\AdapterCountingDecorator:
        arguments:
            $adapter: '@redis_adapter'

    App\Infrastructure\Repository\TweetRepositoryCacheDecorator:
        arguments:
            $cacheItemPool: '@App\Application\Symfony\AdapterCountingDecorator'
    ```
4. Выполняем два одинаковых запроса Get Tweet list из Postman-коллекции v6 для прогрева кэша и появления метрик
5. Заходим в Grafana, добавляем новую панель
6. Добавляем на панель метрики `sumSeries(stats_counts.my_app.cache.hit.*)` и
   `sumSeries(stats_counts.my_app.cache.miss.*)`

### Инвалидация кэша с помощью тэгов

1. В классе `App\Entity\Tweet` добавляем атрибут `ORM\HasLifecycleCallbacks` для класса и атрибуты для методов
   `setCreatedAt()` и `setUpdatedAt()`
    ```php
    #[ORM\PrePersist]
    public function setCreatedAt(): void {
        $this->createdAt = new DateTime();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function setUpdatedAt(): void {
        $this->updatedAt = new DateTime();
    }
    ```
2. В классе `App\Domain\Service\TweetService` исправляем метод `postTweet`
    ```php
    public function postTweet(User $author, string $text): void
    {
        $tweet = new Tweet();
        $tweet->setAuthor($author);
        $tweet->setText($text);
        $author->addTweet($tweet);
        $this->tweetRepository->create($tweet);
    }
    ```
3. В файле `config/services.yaml`
   1. добавляем новое описание сервиса
       ```yaml
       redis_tag_aware_adapter:
           class: Symfony\Component\Cache\Adapter\RedisTagAwareAdapter
           arguments:
               - '@redis_client'
               - 'my_app'
       ```
   2. исправляем описание для сервиса `App\Infrastructure\Repository\TweetRepositoryCacheDecorator`
       ```yaml
       App\Infrastructure\Repository\TweetRepositoryCacheDecorator:
           arguments:
               $cache: '@redis_tag_aware_adapter'
       ```
4. Исправляем класс `App\Infrastructure\Repository\TweetRepositoryCacheDecorator`
    ```php
    <?php
    
    namespace App\Infrastructure\Repository;
    
    use App\Domain\Entity\Tweet;
    use App\Domain\Model\TweetModel;
    use App\Domain\Repository\TweetRepositoryInterface;
    use Psr\Cache\CacheException;
    use Psr\Cache\InvalidArgumentException;
    use Symfony\Contracts\Cache\ItemInterface;
    use Symfony\Contracts\Cache\TagAwareCacheInterface;
    
    class TweetRepositoryCacheDecorator implements TweetRepositoryInterface
    {
        public function __construct(
            private readonly TweetRepository $tweetRepository,
            private readonly TagAwareCacheInterface $cache,
        ) {
        }
    
        /**
         * @throws InvalidArgumentException
         */
        public function create(Tweet $tweet): int
        {
            $result = $this->tweetRepository->create($tweet);
            $this->cache->invalidateTags([$this->getCacheTag()]);
            
            return $result;
        }
    
        /**
         * @return TweetModel[]
         * @throws InvalidArgumentException
         * @throws CacheException
         */
        public function getTweetsPaginated(int $page, int $perPage): array
        {
            return $this->cache->get(
                $this->getCacheKey($page, $perPage),
                function (ItemInterface $item) use ($page, $perPage) {
                    $tweets = $this->tweetRepository->getTweetsPaginated($page, $perPage);
                    $tweetModels = array_map(
                        static fn (Tweet $tweet): TweetModel => new TweetModel(
                            $tweet->getId(),
                            $tweet->getAuthor()->getLogin(),
                            $tweet->getText(),
                            $tweet->getCreatedAt(),
                        ),
                        $tweets
                    );
                    $item->set($tweetModels);
                    $item->tag($this->getCacheTag());
                    
                    return $tweetModels;
                }
            );
        }
    
        private function getCacheKey(int $page, int $perPage): string
        {
            return "tweets_{$page}_$perPage";
        }
        
        private function getCacheTag(): string
        {
            return 'tweets';
        }
    }
    ``` 
5. Добавляем класс `App\Controller\Web\PostTweet\v1\Input\PostTweetDTO`
    ```php
    <?php
    
    namespace App\Controller\Web\PostTweet\v1\Input;
    
    class PostTweetDTO
    {
        public function __construct(
            public readonly int $userId,
            public readonly string $text,
        ) {
        }
    }
    ```
6. Добавляем класс `App\Controller\Web\PostTweet\v1\Manager`
    ```php
    <?php
    
    namespace App\Controller\Web\PostTweet\v1;
    
    use App\Controller\Exception\AccessDeniedException;
    use App\Controller\Web\PostTweet\v1\Input\PostTweetDTO;
    use App\Domain\Service\TweetService;
    use App\Domain\Service\UserService;
    
    class Manager
    {
        public function __construct(
            private readonly UserService $userService,
            private readonly TweetService $tweetService,
        ) {
        }
    
        public function postTweet(PostTweetDTO $tweetDTO): bool
        {
            $user = $this->userService->findUserById($tweetDTO->userId);
            
            if ($user === null) {
                return false;
            }
            
            $this->tweetService->postTweet($user, $tweetDTO->text);
            
            return true;
        }
    }
    ```
6. Добавим класс `App\Controller\Web\PostTweet\v1\Controller`
    ```php
    <?php
    
    namespace App\Controller\Web\PostTweet\v1;
    
    use App\Controller\Web\PostTweet\v1\Input\PostTweetDTO;
    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\HttpKernel\Attribute\AsController;
    use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
    use Symfony\Component\Routing\Attribute\Route;
    
    #[AsController]
    class Controller
    {
        public function __construct(private readonly Manager $manager) {
        }
    
        #[Route(path: 'api/v1/post-tweet', methods: ['POST'])]
        public function __invoke(#[MapRequestPayload]PostTweetDTO $postTweetDTO): Response
        {
            return new JsonResponse(['success' => $this->manager->postTweet($postTweetDTO)]);
        }
    } 
    ```
7. Выполняем запрос Post tweet из Postman-коллекции v7, видим ошибку
8. Заходим в контейнер с приложением командой `docker exec -it php sh`
9. В контейнере выполняем команду `php bin/console doctrine:cache:clear-metadata`
10. Ещё раз выполняем запрос Post tweet из Postman-коллекции v6, видим успешное сохранение
11. В Redis выполняем `flushall`
12. Выполняем несколько запросов Get tweet из Postman-коллекции v7 с разными значениями параметров для прогрева кэша
13. Проверяем, что в Redis есть ключи для твитов, командой `keys *tweets*`
14. Выполняем запрос Post tweet из Postman-коллекции v7
15. Проверяем, что в Redis удалились все ключи, командой `keys *tweets*`
