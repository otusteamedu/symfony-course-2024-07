# Декомпозируем приложение

Запускаем контейнеры командой `docker-compose up -d`

## Выносим в бандл сервис для работы с лентой

1. Исправляем секцию `autoload` в `composer.json`
    ```json
    "psr-4": {
        "App\\": "src/",
        "FeedBundle\\": "feedBundle/src/"
    }
    ```
2. Выполняем команду `composer dump-autoload`
3. Создаём файл `FeedBundle\FeedBundle`
    ```php
    <?php
    
    namespace FeedBundle;
    
    use Symfony\Component\DependencyInjection\ContainerBuilder;
    use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
    use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
    
    class FeedBundle extends AbstractBundle
    {
        public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
        {
            $container->import('../config/services.yaml');
        }
    }
    ```
4. Подключаем наш бандл в файле `config/bundles.php`
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
        FeedBundle\FeedBundle::class => ['all' => true],
    ];
    ```
5. Копируем в пространство имён `FeedBundle` класс `App\Domain\Model\TweetModel`
6. Переносим в пространство имён `FeedBundle` и исправляем класс `App\Domain\DTO\SendNotificationDTO`
    ```php
    <?php
    
    namespace FeedBundle\Domain\DTO;
    
    class SendNotificationDTO
    {
        public function __construct(
            public readonly int $userId,
            public readonly string $text,
            public readonly string $channel,
        ) {
        }
    }
    ```
7. Копируем в пространство имён `FeedBundle` класс `App\Infrastructure\Repository\AbstractRepository`
8. Переносим в пространство имён `FeedBundle` и исправляем класс `App\Infrastructure\Repository\FeedRepository`
    ```php
    <?php
    
    namespace FeedBundle\Infrastructure\Repository;
    
    use App\Domain\Entity\Feed;
    use App\Domain\Entity\User;
    use FeedBundle\Domain\Model\TweetModel;
    
    class FeedRepository extends AbstractRepository
    {
        public function putTweetToReaderFeed(TweetModel $tweet, User $reader): bool
        {
            $feed = $this->ensureFeedForReader($reader);
            if ($feed === null) {
                return false;
            }
            $tweets = $feed->getTweets();
            $tweets[] = $tweet->toFeed();
            $feed->setTweets($tweets);
            $this->flush();
    
            return true;
        }
    
        public function ensureFeedForReader(User $reader): ?Feed
        {
            $feedRepository = $this->entityManager->getRepository(Feed::class);
            $feed = $feedRepository->findOneBy(['reader' => $reader]);
            if (!($feed instanceof Feed)) {
                $feed = new Feed();
                $feed->setReader($reader);
                $feed->setTweets([]);
                $this->store($feed);
            }
    
            return $feed;
        }
    }
    ```
9. Переносим в пространство имён `FeedBundle` и исправляем интерфейс `App\Domain\Bus\SendNotificationBusInterface`
   ```php
   <?php
    
   namespace FeedBundle\Domain\Bus;
    
   use FeedBundle\Domain\DTO\SendNotificationDTO;
    
   interface SendNotificationBusInterface
   {
       public function sendNotification(SendNotificationDTO $sendNotificationDTO): bool;
   }
   ```
10. Переносим в пространство имён `FeedBundle` и исправляем класс `App\Domain\Service\FeedService` (не забываем
    поправить импорт класса в другие сервисы)
    ```php
    <?php
    
    namespace FeedBundle\Domain\Service;
    
    use App\Domain\Bus\PublishTweetBusInterface;
    use App\Domain\Entity\EmailUser;
    use App\Domain\Entity\Subscription;
    use App\Domain\Entity\Tweet;
    use App\Domain\Entity\User;
    use App\Domain\Model\TweetModel as DomainTweetModel;
    use App\Domain\Service\SubscriptionService;
    use App\Domain\ValueObject\CommunicationChannelEnum;
    use App\Infrastructure\Repository\TweetRepository;
    use FeedBundle\Domain\Bus\SendNotificationBusInterface;
    use FeedBundle\Domain\DTO\SendNotificationDTO;
    use FeedBundle\Domain\Model\TweetModel;
    use FeedBundle\Infrastructure\Repository\FeedRepository;
    
    class FeedService
    {
        public function __construct(
            private readonly FeedRepository $feedRepository,
            private readonly SubscriptionService $subscriptionService,
            private readonly PublishTweetBusInterface $publishTweetBus,
            private readonly SendNotificationBusInterface $sendNotificationBus,
            private readonly TweetRepository $tweetRepository,
        ) {
        }
    
        public function ensureFeed(User $user, int $count): array
        {
            $feed = $this->feedRepository->ensureFeedForReader($user);
    
            return $feed === null ? [] : array_slice($feed->getTweets(), -$count);
        }
    
        public function spreadTweetAsync(DomainTweetModel $tweet): void
        {
            $this->publishTweetBus->sendPublishTweetMessage($tweet);
        }
    
        public function spreadTweetSync(TweetModel $tweet): void
        {
            $followers = $this->subscriptionService->getFollowers($tweet->authorId);
    
            foreach ($followers as $follower) {
                $this->materializeTweet($tweet, $follower);
            }
        }
    
        public function materializeTweet(TweetModel $tweet, User $follower): void
        {
            $this->feedRepository->putTweetToReaderFeed($tweet, $follower);
            $sendNotificationDTO = new SendNotificationDTO(
                $follower->getId(),
                $tweet->text,
                $follower instanceof EmailUser ? CommunicationChannelEnum::Email->value : CommunicationChannelEnum::Phone->value
            );
            $this->sendNotificationBus->sendNotification($sendNotificationDTO);
        }
    
        /**
         * @return Tweet[]
         */
        public function getFeedWithoutMaterialization(User $user, int $count): array
        {
            return $this->tweetRepository->getTweetsForAuthorIds(
                array_map(
                    static fn (Subscription $subscription): int => $subscription->getAuthor()->getId(),
                    $user->getSubscriptionAuthors()
                ),
                $count,
            );
        }
    }
    ```
11. Копируем в пространство имён `FeedBundle` класс `App\Infrastructure\Bus\RabbitMqBus`
12. Добавляем перечисление `FeedBundle\Infrastructure\Bus\AmqpExchangeEnum`
    ```php
    <?php
    
    namespace FeedBundle\Infrastructure\Bus;
    
    enum AmqpExchangeEnum: string
    {
        case SendNotification = 'send_notification';
    }
    ```
13. Исправляем перечисление `App\Infrastructure\Bus\AmqpExchangeEnum`
    ```php
    <?php
    
    namespace App\Infrastructure\Bus;
    
    enum AmqpExchangeEnum: string
    {
        case AddFollowers = 'add_followers';
        case PublishTweet = 'publish_tweet';
        case UpdateFeed = 'update_feed';
    }
    ```
14. Переносим в пространство имён `FeedBundle` и исправляем класс `App\Infrastructure\Bus\Adapter\SendNotificationRabbitMqBus`
    ```php
    <?php
    
    namespace FeedBundle\Infrastructure\Bus\Adapter;
    
    use FeedBundle\Domain\Bus\SendNotificationBusInterface;
    use FeedBundle\Domain\DTO\SendNotificationDTO;
    use FeedBundle\Infrastructure\Bus\AmqpExchangeEnum;
    use FeedBundle\Infrastructure\Bus\RabbitMqBus;
    
    class SendNotificationRabbitMqBus implements SendNotificationBusInterface
    {
        public function __construct(private readonly RabbitMqBus $rabbitMqBus)
        {
        }
    
        public function sendNotification(SendNotificationDTO $sendNotificationDTO): bool
        {
            return $this->rabbitMqBus->publishToExchange(
                AmqpExchangeEnum::SendNotification,
                $sendNotificationDTO,
                $sendNotificationDTO->channel
            );
        }
    }
    ```
15. Исправляем файл `App\Domain\Service\TweetService`
    ```php
    <?php
    
    namespace App\Domain\Service;
    
    use App\Domain\Entity\Tweet;
    use App\Domain\Entity\User;
    use App\Domain\Model\TweetModel;
    use App\Domain\Repository\TweetRepositoryInterface;
    use FeedBundle\Domain\Model\TweetModel as FeedTweetModel;
    use FeedBundle\Domain\Service\FeedService;
    
    class TweetService
    {
        public function __construct(
            private readonly TweetRepositoryInterface $tweetRepository,
            private readonly FeedService $feedService,
        ) {
        }
    
        public function postTweet(User $author, string $text, bool $async): void
        {
            $tweet = new Tweet();
            $tweet->setAuthor($author);
            $tweet->setText($text);
            $author->addTweet($tweet);
            $this->tweetRepository->create($tweet);
            if ($async) {
                $tweetModel = new TweetModel(
                    $tweet->getId(),
                    $tweet->getAuthor()->getLogin(),
                    $tweet->getAuthor()->getId(),
                    $tweet->getText(),
                    $tweet->getCreatedAt()
                );
                $this->feedService->spreadTweetAsync($tweetModel);
            } else {
                $tweetModel = new FeedTweetModel(
                    $tweet->getId(),
                    $tweet->getAuthor()->getLogin(),
                    $tweet->getAuthor()->getId(),
                    $tweet->getText(),
                    $tweet->getCreatedAt()
                );
                $this->feedService->spreadTweetSync($tweetModel);
            }
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
16. Добавляем файл `feedBundle/config/services.yaml`
    ```yaml
    services:
      _defaults:
        autowire: true
        autoconfigure: true
    
      FeedBundle\:
        resource: '../src/'
        exclude:
          - '../src/DependencyInjection/'
          - '../src/Entity/'
    
      FeedBundle\Infrastructure\Bus\RabbitMqBus:
        calls:
          - [ 'registerProducer', [ !php/enum FeedBundle\Infrastructure\Bus\AmqpExchangeEnum::SendNotification, '@old_sound_rabbit_mq.send_notification_producer' ] ]
    ```
17. В файле `config/services.yaml` исправляем описание сервиса `App\Infrastructure\Bus\RabbitMqBus`
    ```yaml
    App\Infrastructure\Bus\RabbitMqBus:
        calls:
            - [ 'registerProducer', [ !php/enum App\Infrastructure\Bus\AmqpExchangeEnum::AddFollowers, '@old_sound_rabbit_mq.add_followers_producer' ] ]
            - [ 'registerProducer', [ !php/enum App\Infrastructure\Bus\AmqpExchangeEnum::PublishTweet, '@old_sound_rabbit_mq.publish_tweet_producer' ] ]
            - [ 'registerProducer', [ !php/enum App\Infrastructure\Bus\AmqpExchangeEnum::UpdateFeed, '@old_sound_rabbit_mq.update_feed_producer' ] ]
    ```
18. Перезапускаем контейнер супервизора командой `docker-compose restart supervisor`
19. Выполняем запрос Add user v2 из Postman-коллекции v10 
20. Выполняем запрос Add followers из Postman-коллекции v10
21. Выполняем запрос Post tweet из Postman-коллекции v10
22. Выполняем запрос Get feed из Postman-коллекции v10 для любого добавленного подписчика, видим опубликованный твит

## Переносим сущность Feed в бандл

1. В класс `FeedBundle\FeedBundle` добавляем метод `prependExtension`
    ```php
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->prependExtensionConfig(
            'doctrine',
            [
                'orm' => [
                    'mappings' => [
                        'FeedBundle' => [
                            'type' => 'attribute',
                            'dir' => '%kernel.project_dir%/feedBundle/src/Domain/Entity',
                            'prefix' => 'FeedBundle\Domain\Entity',
                            'alias' => 'FeedBundle'
                        ]
                    ]
                ]
            ]
        );
    }
    ```
2. Копируем в пространство `FeedBundle` интерфейс `App\Domain\Entity\EntityInterface`
3. Переносим в пространство `FeedBundle` и исправляем класс `App\Domain\Entity\Feed`
    ```php
    <?php
    
    namespace FeedBundle\Domain\Entity;
    
    use DateTime;
    use Doctrine\ORM\Mapping as ORM;
    
    #[ORM\Table(name: 'feed')]
    #[ORM\UniqueConstraint(columns: ['reader_id'])]
    #[ORM\Entity]
    #[ORM\HasLifecycleCallbacks]
    class Feed implements EntityInterface
    {
        #[ORM\Column(name: 'id', type: 'bigint', unique:true)]
        #[ORM\Id]
        #[ORM\GeneratedValue(strategy: 'IDENTITY')]
        private int $id;
    
        #[ORM\Column(name: 'reader_id', type: 'bigint')]
        private int $readerId;
    
        #[ORM\Column(type: 'json', nullable: true)]
        private ?array $tweets;
    
        #[ORM\Column(name: 'created_at', type: 'datetime', nullable: false)]
        private DateTime $createdAt;
    
        #[ORM\Column(name: 'updated_at', type: 'datetime', nullable: false)]
        private DateTime $updatedAt;
    
        public function getId(): int
        {
            return $this->id;
        }
    
        public function setId(int $id): void
        {
            $this->id = $id;
        }
    
        public function getReaderId(): int
        {
            return $this->readerId;
        }
    
        public function setReaderId(int $readerId): void
        {
            $this->readerId = $readerId;
        }
    
        public function getTweets(): ?array
        {
            return $this->tweets;
        }
    
        public function setTweets(?array $tweets): void
        {
            $this->tweets = $tweets;
        }
    
        public function getCreatedAt(): DateTime {
            return $this->createdAt;
        }
    
        #[ORM\PrePersist]
        public function setCreatedAt(): void {
            $this->createdAt = new DateTime();
        }
    
        public function getUpdatedAt(): DateTime {
            return $this->updatedAt;
        }
    
        #[ORM\PrePersist]
        #[ORM\PreUpdate]
        public function setUpdatedAt(): void {
            $this->updatedAt = new DateTime();
        }
    }
    ```
4. Исправляем в классе `FeedBundle\Infrastructure\Repository\AbstractRepository` импорт интерфейса `EntityInterface` 
5. Исправляем класс `FeedBundle\Infrastructure\Repository\FeedRepository`
    ```php
    <?php
    
    namespace FeedBundle\Infrastructure\Repository;
    
    use FeedBundle\Domain\Entity\Feed;
    use FeedBundle\Domain\Model\TweetModel;
    
    class FeedRepository extends AbstractRepository
    {
        public function putTweetToReaderFeed(TweetModel $tweet, int $readerId): bool
        {
            $feed = $this->ensureFeedForReader($readerId);
            if ($feed === null) {
                return false;
            }
            $tweets = $feed->getTweets();
            $tweets[] = $tweet->toFeed();
            $feed->setTweets($tweets);
            $this->flush();
    
            return true;
        }
    
        public function ensureFeedForReader(int $readerId): ?Feed
        {
            $feedRepository = $this->entityManager->getRepository(Feed::class);
            $feed = $feedRepository->findOneBy(['readerId' => $readerId]);
            if (!($feed instanceof Feed)) {
                $feed = new Feed();
                $feed->setReaderId($readerId);
                $feed->setTweets([]);
                $this->store($feed);
            }
    
            return $feed;
        }
    }
    ```
6. В классе `FeedBundle\Domain\Service\FeedService`
   1. исправляем метод `ensureFeed`
        ```php
        public function ensureFeed(User $user, int $count): array
        {
            $feed = $this->feedRepository->ensureFeedForReader($user->getId());

            return $feed === null ? [] : array_slice($feed->getTweets(), -$count);
        }
        ```
   2. исправляем метод `materializeTweet`
        ```php
        public function materializeTweet(TweetModel $tweet, User $follower): void
        {
            $this->feedRepository->putTweetToReaderFeed($tweet, $follower->getId());
            $sendNotificationDTO = new SendNotificationDTO(
                $follower->getId(),
                $tweet->text,
                $follower instanceof EmailUser ? CommunicationChannelEnum::Email->value : CommunicationChannelEnum::Phone->value
            );
            $this->sendNotificationBus->sendNotification($sendNotificationDTO);
        }
        ```
7. Выполняем запрос Post tweet из Postman-коллекции v10
8. Выполняем запрос Get feed из Postman-коллекции v10 для любого подписчика, видим корректную ленту

## Переносим консьюмер и продюсер в бандл

1. Исправляем класс `App\Domain\Service\TweetService`
    ```php
    <?php
    
    namespace App\Domain\Service;
    
    use App\Domain\Bus\PublishTweetBusInterface;
    use App\Domain\Entity\Tweet;
    use App\Domain\Entity\User;
    use App\Domain\Model\TweetModel;
    use App\Domain\Repository\TweetRepositoryInterface;
    
    class TweetService
    {
        public function __construct(
            private readonly TweetRepositoryInterface $tweetRepository,
            private readonly PublishTweetBusInterface $publishTweetBus,
        ) {
        }
    
        public function postTweet(User $author, string $text): void
        {
            $tweet = new Tweet();
            $tweet->setAuthor($author);
            $tweet->setText($text);
            $author->addTweet($tweet);
            $this->tweetRepository->create($tweet);
            $tweetModel = new TweetModel(
                $tweet->getId(),
                $tweet->getAuthor()->getLogin(),
                $tweet->getAuthor()->getId(),
                $tweet->getText(),
                $tweet->getCreatedAt()
            );
            $this->publishTweetBus->sendPublishTweetMessage($tweetModel);
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
2. Исправляем класс `FeedBundle\FeedBundle`
    ```php
    <?php
    
    namespace FeedBundle;
    
    use FeedBundle\Controller\Amqp\UpdateFeed\Consumer;
    use Symfony\Component\DependencyInjection\ContainerBuilder;
    use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
    use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
    
    class FeedBundle extends AbstractBundle
    {
        public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
        {
            $container->import('../config/services.yaml');
        }
    
        public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
        {
            $builder->prependExtensionConfig(
                'doctrine',
                [
                    'orm' => [
                        'mappings' => [
                            'FeedBundle' => [
                                'type' => 'attribute',
                                'dir' => '%kernel.project_dir%/feedBundle/src/Domain/Entity',
                                'prefix' => 'FeedBundle\Domain\Entity',
                                'alias' => 'FeedBundle'
                            ]
                        ]
                    ]
                ]
            );
            $builder->prependExtensionConfig(
                'old_sound_rabbit_mq',
                [
                    'producers' => [
                        'send_notification' => [
                            'connection' => 'default',
                            'exchange_options' => ['name' => 'old_sound_rabbit_mq.send_notification', 'type' => 'topic'],
                        ],
                    ],
                    'consumers' => array_merge(
                        ...array_map(
                            fn(int $number): array => $this->makeUpdateFeedConsumerDefinition($number),
                            range(0, 9),
                        )
                    ),
                ]
            );
        }
    
        private function makeUpdateFeedConsumerDefinition(int $number): array
        {
            return [
                "update_feed_$number" => [
                    'connection' => 'default',
                    'exchange_options' => ['name' => 'old_sound_rabbit_mq.update_feed', 'type' => 'x-consistent-hash'],
                    'queue_options' => [
                        'name' => "old_sound_rabbit_mq.consumer.update_feed_$number",
                        'routing_key' => '20'
                    ],
                    'callback' => Consumer::class.$number,
                    'idle_timeout' => 300,
                    'idle_timeout_exit_code' => 0,
                    'graceful_max_execution' => ['timeout' => 1800, 'exit_code' => 0],
                    'qos_options' => ['prefetch_size' => 0, 'prefetch_count' => 1, 'global' => false],
                ]
            ];
        }
    }
    ```
3. Исправляем файл `config/packages/old_sound_rabbit_mq.yaml`
    ```yaml
    old_sound_rabbit_mq:
        connections:
            default:
                url: '%env(RABBITMQ_URL)%'
    
        producers:
            add_followers:
                connection: default
                exchange_options: {name: 'old_sound_rabbit_mq.add_followers', type: direct}
            publish_tweet:
                connection: default
                exchange_options: {name: 'old_sound_rabbit_mq.publish_tweet', type: direct}
            update_feed:
                connection: default
                exchange_options: {name: 'old_sound_rabbit_mq.update_feed', type: x-consistent-hash}
    
        consumers:
            add_followers:
                connection: default
                exchange_options: {name: 'old_sound_rabbit_mq.add_followers', type: direct}
                queue_options: {name: 'old_sound_rabbit_mq.consumer.add_followers'}
                callback: App\Controller\Amqp\AddFollowers\Consumer
                idle_timeout: 300
                idle_timeout_exit_code: 0
                graceful_max_execution:
                    timeout: 1800
                    exit_code: 0
                qos_options: {prefetch_size: 0, prefetch_count: 30, global: false}
            publish_tweet:
                connection: default
                exchange_options: {name: 'old_sound_rabbit_mq.publish_tweet', type: direct}
                queue_options: {name: 'old_sound_rabbit_mq.consumer.publish_tweet'}
                callback: App\Controller\Amqp\PublishTweet\Consumer
                idle_timeout: 300
                idle_timeout_exit_code: 0
                graceful_max_execution:
                    timeout: 1800
                    exit_code: 0
                qos_options: {prefetch_size: 0, prefetch_count: 1, global: false}
            send_notification.email:
                connection: default
                exchange_options: {name: 'old_sound_rabbit_mq.send_notification', type: topic}
                queue_options:
                    name: 'old_sound_rabbit_mq.consumer.send_notification.email'
                    routing_keys: ['email']
                callback: App\Controller\Amqp\SendEmailNotification\Consumer
                idle_timeout: 300
                idle_timeout_exit_code: 0
                graceful_max_execution:
                    timeout: 1800
                    exit_code: 0
                qos_options: {prefetch_size: 0, prefetch_count: 1, global: false}
            send_notification.sms:
                connection: default
                exchange_options: {name: 'old_sound_rabbit_mq.send_notification', type: topic}
                queue_options:
                    name: 'old_sound_rabbit_mq.consumer.send_notification.sms'
                    routing_keys: ['phone']
                callback: App\Controller\Amqp\SendSmsNotification\Consumer
                idle_timeout: 300
                idle_timeout_exit_code: 0
                graceful_max_execution:
                    timeout: 1800
                    exit_code: 0
                qos_options: {prefetch_size: 0, prefetch_count: 1, global: false}
    ```
4. В файле `feedBundle/config/services.yaml` добавляем новые описания сервисов
    ```yaml
    FeedBundle\Controller\Amqp\UpdateFeed\Consumer0:
      class: FeedBundle\Controller\Amqp\UpdateFeed\Consumer
      arguments:
        $key: 'update_feed_0'

    FeedBundle\Controller\Amqp\UpdateFeed\Consumer1:
      class: FeedBundle\Controller\Amqp\UpdateFeed\Consumer
      arguments:
        $key: 'update_feed_1'

    FeedBundle\Controller\Amqp\UpdateFeed\Consumer2:
      class: FeedBundle\Controller\Amqp\UpdateFeed\Consumer
      arguments:
        $key: 'update_feed_2'

    FeedBundle\Controller\Amqp\UpdateFeed\Consumer3:
      class: FeedBundle\Controller\Amqp\UpdateFeed\Consumer
      arguments:
        $key: 'update_feed_3'

    FeedBundle\Controller\Amqp\UpdateFeed\Consumer4:
      class: FeedBundle\Controller\Amqp\UpdateFeed\Consumer
      arguments:
        $key: 'update_feed_4'

    FeedBundle\Controller\Amqp\UpdateFeed\Consumer5:
      class: FeedBundle\Controller\Amqp\UpdateFeed\Consumer
      arguments:
        $key: 'update_feed_5'

    FeedBundle\Controller\Amqp\UpdateFeed\Consumer6:
      class: FeedBundle\Controller\Amqp\UpdateFeed\Consumer
      arguments:
        $key: 'update_feed_6'

    FeedBundle\Controller\Amqp\UpdateFeed\Consumer7:
      class: FeedBundle\Controller\Amqp\UpdateFeed\Consumer
      arguments:
        $key: 'update_feed_7'

    FeedBundle\Controller\Amqp\UpdateFeed\Consumer8:
      class: FeedBundle\Controller\Amqp\UpdateFeed\Consumer
      arguments:
        $key: 'update_feed_8'

    FeedBundle\Controller\Amqp\UpdateFeed\Consumer9:
      class: FeedBundle\Controller\Amqp\UpdateFeed\Consumer
      arguments:
        $key: 'update_feed_9'
    ```
5. В файле `config/services.yaml` удаляем описания сервисов `App\Controller\Amqp\UpdateFeed\ConsumerX`
6. Исправляем класс `FeedBundle\Domain\Service\FeedService`
    ```php
    <?php
    
    namespace FeedBundle\Domain\Service;
    
    use App\Domain\Entity\User;
    use FeedBundle\Domain\Bus\SendNotificationBusInterface;
    use FeedBundle\Domain\DTO\SendNotificationDTO;
    use FeedBundle\Domain\Model\TweetModel;
    use FeedBundle\Infrastructure\Repository\FeedRepository;
    
    class FeedService
    {
        public function __construct(
            private readonly FeedRepository $feedRepository,
            private readonly SendNotificationBusInterface $sendNotificationBus,
        ) {
        }
    
        public function ensureFeed(User $user, int $count): array
        {
            $feed = $this->feedRepository->ensureFeedForReader($user->getId());
    
            return $feed === null ? [] : array_slice($feed->getTweets(), -$count);
        }
    
        public function materializeTweet(TweetModel $tweet, int $followerId, string $channel): void
        {
            $this->feedRepository->putTweetToReaderFeed($tweet, $followerId);
            $sendNotificationDTO = new SendNotificationDTO(
                $followerId,
                $tweet->text,
                $channel
            );
            $this->sendNotificationBus->sendNotification($sendNotificationDTO);
        }
    }
    ```
7. Копируем в пространство `FeedBundle` класс `App\Application\RabbitMq\AbstractConsumer`
8. Переносим в пространство `FeedBundle` и исправляем класс `App\Controller\Amqp\UpdateFeed\Input\Message`
    ```php
    <?php
    
    namespace FeedBundle\Controller\Amqp\UpdateFeed\Input;
    
    use DateTime;
    use Symfony\Component\Validator\Constraints as Assert;
    
    class Message
    {
        public function __construct(
            #[Assert\Type('numeric')]
            public readonly int $id,
            public readonly string $author,
            #[Assert\Type('numeric')]
            public readonly int $authorId,
            public readonly string $text,
            public readonly DateTime $createdAt,
            #[Assert\Type('numeric')]
            public readonly int $followerId,
            public readonly string $followerChannel,
        ) {
        }
    }
    ```
9. Исправляем класс `App\Domain\DTO\UpdateFeedDTO`
    ```php
    <?php
    
    namespace App\Domain\DTO;
    
    use DateTime;
    
    class UpdateFeedDTO
    {
        public function __construct(
            public readonly int $id,
            public readonly string $author,
            public readonly int $authorId,
            public readonly string $text,
            public readonly DateTime $createdAt,
            public readonly int $followerId,
            public readonly string $followerChannel
        ) {
        }
    }
    ```
10. Переносим в пространство `FeedBundle` и исправляем класс `App\Controller\Amqp\UpdateFeed\Consumer`
     ```php
     <?php
    
     namespace FeedBundle\Controller\Amqp\UpdateFeed;
    
     use FeedBundle\Application\RabbitMq\AbstractConsumer;
     use FeedBundle\Domain\Model\TweetModel;
     use FeedBundle\Controller\Amqp\UpdateFeed\Input\Message;
     use FeedBundle\Domain\Service\FeedService;
     use StatsdBundle\Storage\MetricsStorageInterface;
    
     class Consumer extends AbstractConsumer
     {
         public function __construct(
             private readonly FeedService $feedService,
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
             $this->feedService->materializeTweet($tweet, $message->followerId, $message->followerChannel);
             $this->metricsStorage->increment($this->key);
    
             return self::MSG_ACK;
         }
     }
     ``` 
11. В классе `App\Controller\Amqp\PublishTweet\Consumer` исправляем метод `handle`
     ```php
     /**
      * @param Message $message
      */
     protected function handle($message): int
     {
         $followers = $this->subscriptionService->getFollowers($message->authorId);
         foreach ($followers as $follower) {
             $updateFeedDTO = new UpdateFeedDTO(
                 $message->id,
                 $message->author,
                 $message->authorId,
                 $message->text,
                 $message->createdAt,
                 $follower->getId(),
                 $follower instanceof EmailUser ?
                     CommunicationChannelEnum::Email->value : CommunicationChannelEnum::Phone->value,
             );
             $this->updateFeedBus->sendUpdateFeedMessage($updateFeedDTO);
         }

         return self::MSG_ACK;
     }
     ```
12. Перезапускаем контейнер супервизора командой `docker-compose restart supervisor`
13. Выполняем запрос Post tweet из Postman-коллекции v10
14. Выполняем запрос Get feed из Postman-коллекции v10 для любого подписчика, видим корректную ленту
 
## Добавляем фасад

1. Добавляем интерфейс `App\Domain\FeedServiceInterface`
    ```php
    <?php
    
    namespace App\Domain\Service;
    
    use App\Domain\Entity\User;
    
    interface FeedServiceInterface
    {
        public function ensureFeed(User $user, int $count): array;
    }
    ```
2. Добавляем класс `App\Domain\Service\FeedFacade`
    ```php
    <?php
    
    namespace App\Domain\Service;
    
    use App\Domain\Entity\User;
    use FeedBundle\Domain\Service\FeedService;
    
    class FeedFacade implements FeedServiceInterface
    {
        public function __construct(
            private readonly FeedService $feedService,
        ) {
            
        }
        
        public function ensureFeed(User $user, int $count): array
        {
            return $this->feedService->ensureFeed($user->getId(), $count);
        }
    }
    ```
3. В классе `FeedBundle\Domain\Service\FeedService` исправляем метод `ensureFeed`
    ```php
    public function ensureFeed(int $userId, int $count): array
    {
        $feed = $this->feedRepository->ensureFeedForReader($userId);

        return $feed === null ? [] : array_slice($feed->getTweets(), -$count);
    }
    ```
4. В классе `App\Controller\Web\GetFeed\v1\Manager` исправляем зависимость на интерфейс
5. Выполняем запрос Get feed из Postman-коллекции v10 для автора, видим пустую ленту в ответе и в БД

## Переходим на общение по HTTP

1. Копируем в пространство `FeedBundle` класс `App\Controller\Web\GetFeed\v1\Output\Response`
2. Копируем в пространство `FeedBundle` класс `App\Controller\Web\GetFeed\v1\Output\TweetDTO`
3. Добавляем класс `FeedBundle\Controller\Web\GetFeed\v1\Manager`
    ```php
    <?php
    
    namespace FeedBundle\Controller\Web\GetFeed\v1;
    
    use FeedBundle\Controller\Web\GetFeed\v1\Output\Response;
    use FeedBundle\Controller\Web\GetFeed\v1\Output\TweetDTO;
    use FeedBundle\Domain\Service\FeedService;
    
    class Manager
    {
        private const DEFAULT_FEED_SIZE = 20;
    
        public function __construct(private readonly FeedService $feedService)
        {
        }
    
        public function getFeed(int $userId, ?int $count = null): Response
        {
            return new Response(
                array_map(
                    static fn (array $tweetData): TweetDTO => new TweetDTO(...$tweetData),
                    $this->feedService->ensureFeed($userId, $count ?? self::DEFAULT_FEED_SIZE),
                )
            );
        }
    }
    ```
4. Добавляем класс `FeedBundle\Controller\Web\GetFeed\v1\Controller`
    ```php
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
    ```
5. Добавляем интерфейс `App\Domain\Repository\FeedRepositoryInterface`
    ```php
    <?php
    
    namespace App\Domain\Repository;
    
    use App\Domain\Entity\User;
    
    interface FeedRepositoryInterface
    {
        public function ensureFeed(int $userId, int $count): array;
    }
    ```
6. Добавляем класс `App\Infrastructure\Repository\FeedRepository`
    ```php
    <?php
    
    namespace App\Infrastructure\Repository;
    
    use App\Domain\Repository\FeedRepositoryInterface;
    use GuzzleHttp\Client;
    
    class FeedRepository implements FeedRepositoryInterface
    {
        public function __construct(
            private readonly Client $client,
            private readonly string $baseUrl
        ) {
        }
    
        public function ensureFeed(int $userId, int $count): array
        {
            $response = $this->client->get("{$this->baseUrl}/server-api/v1/get-feed/$userId", [
                'query' => [
                    'count' => $count,
                ],
            ]);
            $responseData = json_decode($response->getBody(), true);
    
            return $responseData['tweets'];
        }
    }
    ```
7. Исправляем класс `App\Domain\Service\FeedFacade`
    ```php
    <?php
    
    namespace App\Domain\Service;
    
    use App\Domain\Entity\User;
    use App\Domain\Repository\FeedRepositoryInterface;
    
    class FeedFacade implements FeedServiceInterface
    {
        public function __construct(
            private readonly FeedRepositoryInterface $feedRepository,
        ) {
    
        }
    
        public function ensureFeed(User $user, int $count): array
        {
            return $this->feedRepository->ensureFeed($user->getId(), $count);
        }
    }
    ```
8. В файле `config/services.yaml` добавляем сервисы
    ```
    feed_http_client:
        class: GuzzleHttp\Client
    
    App\Infrastructure\Repository\FeedRepository:
        arguments:
            - '@feed_http_client'
            - 'http://nginx:80'
    ```
9. В файл `config/routes.yaml` добавляем
    ```yaml
    server_api:
        resource:
            path: ../feedBundle/src/Controller/
            namespace: FeedBundle\Controller
        type: attribute
    ```
10. Выполняем запрос Get feed из Postman-коллекции v10 для любого подписчика и видим, что лента возвращается
