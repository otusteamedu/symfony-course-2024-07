# Symfony Bundles и пакеты

Запускаем контейнеры командой `docker-compose up -d`

## Выносим хранилище метрик в бандл

1. Заходим в контейнер командой `docker exec -it php sh`. Дальнейшие команды выполняются из контейнера
2. Исправляем секцию `autoload` в `composer.json`
    ```json
    "psr-4": {
        "App\\": "src/",
        "StatsdBundle\\": "statsdBundle/src"
    }
    ```
3. Добавляем класс `StatsdBundle\Storage\MetricsStorageInterface`
    ```php
    <?php
    
    namespace StatsdBundle\Storage;
    
    interface MetricsStorageInterface
    {
        public function increment(string $key, ?float $sampleRate = null, ?array $tags = null): void;
    }
    ```
4. Переносим класс `App\Infrastructure\Storage\MetricsStorage` в пространство имён `StatsdBundle\Storage` и исправляем
    ```php
    <?php
    
    namespace StatsdBundle\Storage;
    
    use Domnikl\Statsd\Client;
    use Domnikl\Statsd\Connection\UdpSocket;
    
    class MetricsStorage implements MetricsStorageInterface
    {
        private const DEFAULT_SAMPLE_RATE = 1.0;
    
        private Client $client;
    
        public function __construct(string $host, int $port, string $namespace)
        {
            $connection = new UdpSocket($host, $port);
            $this->client = new Client($connection, $namespace);
        }
    
        public function increment(string $key, ?float $sampleRate = null, ?array $tags = null): void
        {
            $this->client->increment($key, $sampleRate ?? self::DEFAULT_SAMPLE_RATE, $tags ?? []);
        }
    }
    ```
5. В файле `config/services.yaml` в секции `services` убираем описание сервиса
   `App\Infrastructure\Storage\MetricsStorage`
6. Создаём файл `statsdBundle/config/services.yaml`
    ```yaml
    services:

      statsd.metrics_storage:
        class: StatsdBundle\Storage\MetricsStorage
        arguments:
          - graphite
          - 8125
          - my_app

      StatsdBundle\Storage\MetricsStorageInterface:
        alias: 'statsd.metrics_storage'
    ```
7. Создаём файл `StatsdBundle\StatsdBundle`
    ```php
    <?php
    
    namespace StatsdBundle;
    
    use Symfony\Component\DependencyInjection\ContainerBuilder;
    use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
    use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
    
    class StatsdBundle extends AbstractBundle
    {
        public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
        {
            $container->import('../config/services.yaml');
        }
    }
    ```
8. Подключаем наш бандл в файле `config/bundles.php`
    ```php
    <?php
   
    return [
        Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
        Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
        Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => ['all' => true],
        Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
        Symfony\WebpackEncoreBundle\WebpackEncoreBundle::class => ['all' => true],
        Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
        Symfony\Bundle\MakerBundle\MakerBundle::class => ['dev' => true],
        Lexik\Bundle\JWTAuthenticationBundle\LexikJWTAuthenticationBundle::class => ['all' => true],
        ApiPlatform\Symfony\Bundle\ApiPlatformBundle::class => ['all' => true],
        Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
        OldSound\RabbitMqBundle\OldSoundRabbitMqBundle::class => ['all' => true],
        FOS\ElasticaBundle\FOSElasticaBundle::class => ['all' => true],
        Nelmio\ApiDocBundle\NelmioApiDocBundle::class => ['all' => true],
        StatsdBundle\StatsdBundle::class => ['all' => true],
    ];
    ```
9. Исправляем класс `App\Domain\EventSubscriber\UserEventSubscriber`
    ```php
    <?php
    
    namespace App\Domain\EventSubscriber;
    
    use App\Domain\Event\CreateUserEvent;
    use App\Domain\Event\UserIsCreatedEvent;
    use App\Domain\Service\UserService;
    use Psr\Log\LoggerInterface;
    use StatsdBundle\Storage\MetricsStorageInterface;
    use Symfony\Component\EventDispatcher\EventSubscriberInterface;
    
    class UserEventSubscriber implements EventSubscriberInterface
    {
        private const USER_CREATED_METRIC = 'user_created';
    
        public function __construct(
            private readonly UserService $userService,
            private readonly LoggerInterface $elasticsearchLogger,
            private readonly MetricsStorageInterface $metricsStorage,
        ) {
        }
    
        public static function getSubscribedEvents(): array
        {
            return [
                CreateUserEvent::class => 'onCreateUser',
                UserIsCreatedEvent::class => 'onUserIsCreated',
            ];
        }
    
        public function onCreateUser(CreateUserEvent $event): void
        {
            $user = null;
    
            if ($event->phone !== null) {
                $user = $this->userService->createWithPhone($event->login, $event->phone);
            } elseif ($event->email !== null) {
                $user = $this->userService->createWithEmail($event->login, $event->email);
            }
    
            $event->id = $user?->getId();
    
        }
    
        public function onUserIsCreated(UserIsCreatedEvent $event): void
        {
            $this->elasticsearchLogger->info("User is created: id {$event->id}, login {$event->login}");
            $this->metricsStorage->increment(self::USER_CREATED_METRIC);
        }
    }
    ```
10. Исправляем класс `App\Application\Symfony\AdapterCountingDecorator`
     ```php
     <?php
    
     namespace App\Application\Symfony;
    
     use Psr\Cache\CacheItemInterface;
     use Psr\Cache\InvalidArgumentException;
     use Psr\Log\LoggerAwareInterface;
     use Psr\Log\LoggerInterface;
     use StatsdBundle\Storage\MetricsStorageInterface;
     use Symfony\Component\Cache\Adapter\AbstractAdapter;
     use Symfony\Component\Cache\Adapter\AdapterInterface;
     use Symfony\Component\Cache\CacheItem;
     use Symfony\Component\Cache\ResettableInterface;
     use Symfony\Contracts\Cache\CacheInterface;
    
     class AdapterCountingDecorator implements AdapterInterface, CacheInterface, LoggerAwareInterface, ResettableInterface
     {
         private const CACHE_HIT_METRIC_PREFIX = 'cache.hit.';
         private const CACHE_MISS_METRIC_PREFIX = 'cache.miss.';
        
         public function __construct(
             private readonly AbstractAdapter $adapter,
             private readonly MetricsStorageInterface $metricsStorage,
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
                 $this->metricsStorage->increment(self::CACHE_HIT_METRIC_PREFIX.$cacheItem->getKey());
             } else {
                 $this->metricsStorage->increment(self::CACHE_MISS_METRIC_PREFIX.$cacheItem->getKey());
             }
         }
     }
     ```
11. Исправляем класс `App\Controller\Amqp\UpdateFeed\Consumer`
     ```php
     <?php
     
     namespace App\Controller\Amqp\UpdateFeed;
     
     use App\Application\RabbitMq\AbstractConsumer;
     use App\Controller\Amqp\UpdateFeed\Input\Message;
     use App\Domain\Entity\User;
     use App\Domain\Model\TweetModel;
     use App\Domain\Service\FeedService;
     use App\Domain\Service\UserService;
     use StatsdBundle\Storage\MetricsStorageInterface;
     
     class Consumer extends AbstractConsumer
     {
         public function __construct(
             private readonly FeedService $feedService,
             private readonly UserService $userService,
             private readonly MetricsStorageInterface $metricsStorage,
             private readonly string $key,
         ) {
         }
     
         protected function getMessageClass(): string
         {
             return Message::class;
         }
     
         /**
          * @param Message $message
          */
         protected function handle($message): int
         {
             $tweet = new TweetModel(
                 $message->id,
                 $message->author,
                 $message->authorId,
                 $message->text,
                 $message->createdAt,
             );
             $user = $this->userService->findUserById($message->followerId);
             if (!($user instanceof User)) {
                 $this->reject('User {$message->followerId} was not found');
             }
             $this->feedService->materializeTweet($tweet, $user);
             $this->metricsStorage->increment($this->key);
     
             return self::MSG_ACK;
         }
     }
     ```
12. В контейнере выполняем команду `composer dump-autoload`
13. Выполняем запрос Add user v2 из Postman-коллекции v10 и проверяем, что данные поступают в Graphite

## Добавляем конфигурацию в бандл

1. Исправляем класс `StatsdBundle\StatsdBundle`
    ```php
    <?php
    
    namespace StatsdBundle;
    
    use StatsdBundle\Storage\MetricsStorageInterface;
    use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
    use Symfony\Component\DependencyInjection\ContainerBuilder;
    use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
    use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
    
    class StatsdBundle extends AbstractBundle
    {
        public function configure(DefinitionConfigurator $definition): void
        {
            $definition->rootNode()
                ->children()
                    ->arrayNode('client')
                        ->children()
                            ->scalarNode('host')
                                ->isRequired()
                                ->cannotBeEmpty()
                                ->end()
                            ->scalarNode('port')
                                ->isRequired()
                                ->cannotBeEmpty()
                                ->end()
                            ->scalarNode('namespace')
                                ->isRequired()
                                ->cannotBeEmpty()
                                ->end()
                        ->end()
                    ->end()
                ->end();
        }
    
    
        public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
        {
            $container->import('../config/services.yaml');
    
            $container->services()
                ->get('statsd.metrics_storage')
                ->arg(0, $config['client']['host'])
                ->arg(1, $config['client']['port'])
                ->arg(2, $config['client']['namespace']);
        }
    }
    ```
2. Добавляем файл `config/packages/statsd.yaml`
    ```yaml
    statsd:
      client:
        host: graphite
        port: 8125
        namespace: my_app
    ```
3. В файле `statsdBundle/config/services.yaml` исправляем описание сервиса `statsd.metrics_storage`
    ```yaml
    statsd.metrics_storage:
      class: StatsdBundle\Storage\MetricsStorage
    ```
4. В контейнере очищаем кэш командой `php bin/console cache:clear`
5. Выполняем ещё один запрос Add user v2 из Postman-коллекции v10 и проверяем, что данные поступают в Graphite

## Выносим бандл в отдельный репозиторий и добавляем рецепт

1. Создаём новый репозиторий для рецептов в GitHub и клонируем его локально
2. В новом репозитории создаём файл `statsd.bundle.1.0.json`
    ```json
    {
      "manifests": {
        "otusteamedu/statsd-bundle": {
          "manifest": {
            "bundles": {
              "StatsdBundle\\StatsdBundle": ["all"]
            },
            "copy-from-recipe": {
              "config/": "%CONFIG_DIR%"
            },
            "env": {
              "STATSD_HOST": "graphite",
              "STATSD_PORT": "8125",
              "STATSD_NAMESPACE": "my_app"
            }
          },
          "files": {
            "config/packages/statsd.yaml": {
              "contents": [
                "statsd:",
                "  client:",
                "    host: %env(STATSD_HOST)%",
                "    port: %env(STATSD_PORT)%",
                "    namespace: %env(STATSD_NAMESPACE)%"
              ],
              "executable": false
            }
          },
          "ref": "35e18ca78b9718d2afca62b3ec670ad36e77195c"
        }
      }
    }
    ```
3. В новом репозитории создаём файл `index.json`
    ```json
    {
      "recipes": {
        "otusteamedu/statsd-bundle": [
          "1.0"
        ]
      },
      "branch": "main",
      "is_contrib": true,
      "_links": {
        "repository": "github.com/otusteamedu/statsd-bundle/",
        "origin_template": "{package}:{version}@github.com/otusteamedu/statsd-bundle:main",
        "recipe_template": "https://api.github.com/repos/otusteamedu/symfony-recipes/contents/statsd.bundle.1.0.json"
      }
    }
    ```
4. Пушим файлы в удалённый репозиторий
5. Создаём новый репозиторий для бандла в GitHub и переносим в него всё содержимое каталога `statsdBundle` из основного
   репозитория проекта
6. В основном репозитории проекта
   1. Убираем загрузку бандла `StatsdBundle` из файла `config/bundles.php`
   2. Удаляем файл `config/packages/statsd.yaml`
7. В новом репозитории создаём в корне файл `composer.json`
    ```json
    {
      "name": "otusteamedu/statsd-bundle",
      "description": "Provides configured MetricsStorage to send metrics to graphite",
      "type": "symfony-bundle",
      "license": "MIT",
      "require": {
        "php": ">=8.3",
        "slickdeals/statsd": "^3.2"
      },
      "autoload": {
        "psr-4": {
          "StatsdBundle\\": "src/"
        }
      }
    }
    ```
8. Пушим новый проект в репозиторий
9. Создаём тэг `1.0`
10. В основном репозитории проекта в файле `composer.json`
    1. исправляем секцию `autoload`
        ```json
        "psr-4": {
            "App\\": "src/"
        }
        ```
    2. в секцию `extra.symfony` добавляем новый ключ
        ```json
        "endpoint": [
            "https://api.github.com/repos/otusteamedu/symfony-recipes/contents/index.json",
            "flex://defaults"
        ]
        ```
    3. добавляем секцию `repositories`
        ```json
        "repositories": [
            {
                "type": "vcs",
                "url": "git@github.com:otusteamedu/statsd-bundle.git"
            }
        ]
        ```
11. В основном репозитории проекта удаляем пакет `slickdeals/statsd`
12. Устанавливаем пакет `otusteamedu/statsd-bundle`, соглашаемся на выполнение рецепта
13. Выполняем ещё один запрос Add user v2 из Postman-коллекции v10 и проверяем, что данные всё ещё поступают в Graphite
