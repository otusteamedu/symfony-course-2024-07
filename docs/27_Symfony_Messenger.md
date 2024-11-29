# Symfony Messenger

## Установка Symfony Messenger

1. Исправляем файл `docker/Dockerfile`
    ```dockerfile
    FROM php:8.3-fpm-alpine
    
    # Install dev dependencies
    RUN apk update \
        && apk upgrade --available \
        && apk add --virtual build-deps \
            autoconf \
            build-base \
            icu-dev \
            libevent-dev \
            openssl-dev \
            zlib-dev \
            libzip \
            libzip-dev \
            zlib \
            zlib-dev \
            bzip2 \
            git \
            libpng \
            libpng-dev \
            libjpeg \
            libjpeg-turbo-dev \
            libwebp-dev \
            freetype \
            freetype-dev \
            postgresql-dev \
            linux-headers \
            libmemcached-dev \
            rabbitmq-c \
            rabbitmq-c-dev \
            curl \
            wget \
            bash
    
    # Install Composer
    RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer
    
    # Install PHP extensions
    RUN docker-php-ext-configure gd --with-freetype=/usr/include/ --with-jpeg=/usr/include/
    RUN docker-php-ext-install -j$(getconf _NPROCESSORS_ONLN) \
        intl \
        gd \
        bcmath \
        pcntl \
        pdo_pgsql \
        sockets \
        zip
    RUN pecl channel-update pecl.php.net \
        && pecl install -o -f \
            amqp \
            memcached \
            redis \
            event \
        && rm -rf /tmp/pear \
        && echo "extension=amqp.so" > /usr/local/etc/php/conf.d/amqp.ini \
        && echo "extension=redis.so" > /usr/local/etc/php/conf.d/redis.ini \
        && echo "extension=event.so" > /usr/local/etc/php/conf.d/event.ini \
        && echo "extension=memcached.so" > /usr/local/etc/php/conf.d/memcached.ini
    ```
2. Исправляем файл `docker/supervisor/Dockerfile`
    ```dockerfile
    FROM php:8.3-cli-alpine
    
    # Install dev dependencies
    RUN apk update \
        && apk upgrade --available \
        && apk add --virtual build-deps \
            autoconf \
            build-base \
            icu-dev \
            libevent-dev \
            openssl-dev \
            zlib-dev \
            libzip \
            libzip-dev \
            zlib \
            zlib-dev \
            bzip2 \
            git \
            libpng \
            libpng-dev \
            libjpeg \
            libjpeg-turbo-dev \
            libwebp-dev \
            freetype \
            freetype-dev \
            postgresql-dev \
            linux-headers \
            libmemcached-dev \
            rabbitmq-c \
            rabbitmq-c-dev \
            curl \
            wget \
            bash
    
    # Install Composer
    RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer
    
    # Install PHP extensions
    RUN docker-php-ext-configure gd --with-freetype=/usr/include/ --with-jpeg=/usr/include/
    RUN docker-php-ext-install -j$(getconf _NPROCESSORS_ONLN) \
        intl \
        gd \
        bcmath \
        pcntl \
        pdo_pgsql \
        sockets \
        zip
    RUN pecl channel-update pecl.php.net \
        && pecl install -o -f \
            amqp \
            memcached \
            redis \
            event \
        && rm -rf /tmp/pear \
        && echo "extension=amqp.so" > /usr/local/etc/php/conf.d/amqp.ini \
        && echo "extension=redis.so" > /usr/local/etc/php/conf.d/redis.ini \
        && echo "extension=event.so" > /usr/local/etc/php/conf.d/event.ini \
        && echo "extension=memcached.so" > /usr/local/etc/php/conf.d/memcached.ini
    
    RUN apk add supervisor && mkdir /var/log/supervisor
    ```
3. Запускаем контейнеры командой `docker-compose up -d --build`
4. Заходим в контейнер командой `docker exec -it php sh`. Дальнейшие команды выполняются из контейнера
5. Устанавливаем пакеты `symfony/messenger`, `symfony/doctrine-messenger` и `symfony/amqp-messenger`
6. В файле `.env` раскомментируем и исправляем переменные с DSN для транспорта
    ```shell
    MESSENGER_DOCTRINE_TRANSPORT_DSN=doctrine://default
    MESSENGER_AMQP_TRANSPORT_DSN=amqp://user:password@rabbit-mq:5672/%2f/messages
    ```
7. В файле `config/packages/messenger.yaml` исправляем секцию `framework.messenger.transports`
    ```yaml
    doctrine:
        dsn: "%env(MESSENGER_DOCTRINE_TRANSPORT_DSN)%"
    amqp:
        dsn: "%env(MESSENGER_AMQP_TRANSPORT_DSN)%"
    sync: 'sync://'    
    ``` 

## Отправляем сообщение через Symfony Messenger

1. Добавляем класс `App\Infrastructure\Bus\Adapter\AddFollowersMessengerBus`
    ```php
    <?php
    
    namespace App\Infrastructure\Bus\Adapter;
    
    use App\Domain\Bus\AddFollowersBusInterface;
    use App\Domain\DTO\AddFollowersDTO;
    use Symfony\Component\Messenger\MessageBusInterface;
    
    class AddFollowersMessengerBus implements AddFollowersBusInterface
    {
        public function __construct(private readonly MessageBusInterface $messageBus)
        {
        }
    
        public function sendAddFollowersMessage(AddFollowersDTO $addFollowersDTO): bool
        {
            for ($i = 0; $i < $addFollowersDTO->count; $i++) {
                $this->messageBus->dispatch(new AddFollowersDTO($addFollowersDTO->userId, $addFollowersDTO->followerLogin."_$i", 1));
            }

            return true;
        }
    }
    ```
2. В файле `config/services.yaml` добавляем новый алиас сервиса:
    ```yaml
    App\Domain\Bus\AddFollowersBusInterface:
        alias: App\Infrastructure\Bus\Adapter\AddFollowersMessengerBus
    ```
3. В файле `config/packages/messenger.yaml`
   1. исправляем секцию `framework.messenger.transports.amqp`
       ```yaml
       dsn: "%env(MESSENGER_AMQP_TRANSPORT_DSN)%"
       options:
           exchange:
               name: 'old_sound_rabbit_mq.add_followers'
               type: direct
       ```
   2. исправляем секцию `framework.messenger.routing`
       ```yaml
       App\Domain\DTO\AddFollowersDTO: amqp
       ```
4. Перезапускаем контейнер супервизора командой `docker-compose restart supervisor`
5. Выполняем запрос Add followers из Postman-коллекции v10 с параметром async = 1. Проверяем, в интерфейсе RabbitMQ, что
сообщения попали в очередь, однако пользователи в БД не появились.
6. В интерфейсе RabbitMQ в очереди `messages` можем просмотреть сообщения и увидеть, что они сериализуются не в JSON.

## Исправляем сериализацию сообщения

1. В файле `config/packages/messenger.yaml` исправляем секцию `framework.messenger.transports.amqp`
    ```yaml
    amqp:
        dsn: "%env(MESSENGER_AMQP_TRANSPORT_DSN)%"
        options:
            exchange:
                name: 'old_sound_rabbit_mq.add_followers'
                type: direct
        serializer: 'messenger.transport.symfony_serializer'
    ```
2. Ещё раз выполняем запрос Add followers из Postman-коллекции v10 с параметром async = 1. Видим, что пользователи
добавились в БД.

## Отправляем сообщение с routingKey

1. Добавляем класс `FeedBundle\Infrastructure\Bus\Adapter\SendNotificationMessengerBus`
    ```php
    <?php
    
    namespace FeedBundle\Infrastructure\Bus\Adapter;
    
    use FeedBundle\Domain\Bus\SendNotificationBusInterface;
    use FeedBundle\Domain\DTO\SendNotificationDTO;
    use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
    use Symfony\Component\Messenger\Envelope;
    use Symfony\Component\Messenger\MessageBusInterface;
    
    class SendNotificationMessengerBus implements SendNotificationBusInterface
    {
        public function __construct(private readonly MessageBusInterface $messageBus)
        {
        }
    
        public function sendNotification(SendNotificationDTO $sendNotificationDTO): bool
        {
            $this->messageBus->dispatch(
                new Envelope($sendNotificationDTO, [new AmqpStamp($sendNotificationDTO->channel)]),
            );
            
            return true;
        }
    }
    ```
2. В файле `feedBundle/config/services.yaml` добавляем новый алиас сервиса:
    ```yaml
    FeedBundle\Domain\Bus\SendNotificationBusInterface:
        alias: FeedBundle\Infrastructure\Bus\Adapter\SendNotificationMessengerBus
    ```
3. Исправляем файл `config/packages/messenger.yaml`
    ```yaml
    framework:
        messenger:
            transports:
                doctrine:
                    dsn: "%env(MESSENGER_DOCTRINE_TRANSPORT_DSN)%"
                add_followers:
                    dsn: "%env(MESSENGER_AMQP_TRANSPORT_DSN)%"
                    options:
                        exchange:
                            name: 'old_sound_rabbit_mq.add_followers'
                            type: direct
                    serializer: 'messenger.transport.symfony_serializer'
                sync: 'sync://'
    
            routing:
                App\Domain\DTO\AddFollowersDTO: add_followers
    ```
4. В классе `FeedBundle\FeedBundle` исправляем метод `prependExtension`
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
        
        $builder->prependExtensionConfig(
            'framework',
            [
                'messenger' => [
                    'transports' => [
                        'send_notification' => [
                            'dsn' => '%env(MESSENGER_AMQP_TRANSPORT_DSN)%',
                            'options' => [
                                'exchange' => ['name' => 'old_sound_rabbit_mq.send_notification', 'type' => 'topic'],
                            ],
                            'serializer' => 'messenger.transport.symfony_serializer',
                        ],
                    ],
                    'routing' => [
                        SendNotificationDTO::class => 'send_notification',
                    ]
                ],
            ],
        );
    }
    ```
5. Перезапускаем контейнер супервизора командой `docker-compose restart supervisor`
6. Выполняем запрос Post tweet из Postman-коллекции v10 с параметром async = 1. Видим, что уведомления добавились в БД.

## Имитируем проблему с отправкой сообщения

1. В классе `FeedBundle\Domain\Service\FeedService` исправляем метод `materializeTweet`
    ```php
    public function materializeTweet(TweetModel $tweet, int $followerId, string $channel): void
    {
        $this->feedRepository->putTweetToReaderFeed($tweet, $followerId);
        if ($followerId === 5) {
            sleep(2);
            throw new RuntimeException();
        }
        $sendNotificationDTO = new SendNotificationDTO(
            $followerId,
            $tweet->text,
            $channel
        );
        $this->sendNotificationBus->sendNotification($sendNotificationDTO);
    }
    ```
2. В классе `FeedBundle\Controller\Amqp\UpdateFeed\Consumer` исправляем метод `handle`
    ```php
    protected function handle($message): int
    {
        $tweet = new TweetModel(
            $message->id,
            $message->author,
            $message->authorId,
            $message->text,
            $message->createdAt,
        );
        try {
            $this->feedService->materializeTweet($tweet, $message->followerId, $message->followerChannel);
        } catch (RuntimeException) {
            return self::MSG_REJECT_REQUEUE;
        }
        $this->metricsStorage->increment($this->key);

        return self::MSG_ACK;
    }
    ```
3. Перезапускаем контейнер supervisor командой `docker-compose restart supervisor`
4. Выполняем запрос Post tweet из Postman-коллекции v10 с параметром async = 1. Видим, что лента для пользователя с
   `id = 5` растёт, но уведомления не отправляются.

## Добавляем транзакционную отправку сообщения

1. В классе `FeedBundle\Infrastructure\Repository\AbstractRepository` добавляем метод
    ```php
    public function transactional(callable $callable): void
    {
        try {
            $this->entityManager->getConnection()->beginTransaction();
            $callable();
            $this->entityManager->getConnection()->commit();
        } catch (Throwable $e) {
            $this->entityManager->getConnection()->rollBack();
            throw $e;
        }
    }
    ```
2. В классе `FeedBundle\Domain\Service\FeedService` исправляем метод `materializeTweet`
    ```php
    public function materializeTweet(TweetModel $tweet, int $followerId, string $channel): void
    {
        $this->feedRepository->transactional(
            function () use ($tweet, $followerId, $channel) {
                $this->feedRepository->putTweetToReaderFeed($tweet, $followerId);
                if ($followerId === 5) {
                    sleep(2);
                    throw new RuntimeException();
                }
                $sendNotificationDTO = new SendNotificationDTO(
                    $followerId,
                    $tweet->text,
                    $channel
                );
                $this->sendNotificationBus->sendNotification($sendNotificationDTO);
            },
        );
    }
    ```
3. В файле `FeedBundle\FeedBundle` исправляем метод `prependExtension`
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

        $builder->prependExtensionConfig(
            'framework',
            [
                'messenger' => [
                    'transports' => [
                        'send_notification' => [
                            'dsn' => '%env(MESSENGER_AMQP_TRANSPORT_DSN)%',
                            'options' => [
                                'exchange' => ['name' => 'old_sound_rabbit_mq.send_notification', 'type' => 'topic'],
                            ],
                            'serializer' => 'messenger.transport.symfony_serializer',
                        ],
                    ],
                    'routing' => [
                        SendNotificationDTO::class => 'doctrine',
                    ]
                ],
            ],
        );
    }
    ```
4. В файле `config/packages/messenger.yaml` исправляем секцию `framework.messenger.transports.doctrine`
    ```yaml
    doctrine:
        dsn: "%env(MESSENGER_DOCTRINE_TRANSPORT_DSN)%"
        serializer: 'messenger.transport.symfony_serializer'
    ```
5. Выполняем команду `php bin/console doctrine:migrations:diff` для создания таблицы для сообщений
6. Проверяем получившуюся миграцию и применяем её командой `php bin/console doctrine:migrations:migrate`
7. Останавливаем контейнер supervisor командой `docker-compose stop supervisor`
8. Очищаем очередь, в которой "зависли" сообщения
9. Запускаем контейнер supervisor командой `docker-compose restart supervisor`
10. Выполняем запрос Post tweet из Postman-коллекции v10 с параметром async = 1. Видим, что лента для пользователя с
    `id = 5` больше не растёт, и уведомления не отправляются.

## Добавляем обработчик

1. Копируем класс `FeedBundle\Domain\DTO\SendNotificationDTO` в
   `FeedBundle\Domain\DTO\SendNotificationAsyncDTO`
2. В файле `FeedBundle\FeedBundle` исправляем метод `prependExtension`
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

        $builder->prependExtensionConfig(
            'framework',
            [
                'messenger' => [
                    'transports' => [
                        'send_notification' => [
                            'dsn' => '%env(MESSENGER_AMQP_TRANSPORT_DSN)%',
                            'options' => [
                                'exchange' => ['name' => 'old_sound_rabbit_mq.send_notification', 'type' => 'topic'],
                            ],
                            'serializer' => 'messenger.transport.symfony_serializer',
                        ],
                    ],
                    'routing' => [
                        SendNotificationDTO::class => 'doctrine',
                        SendNotificationAsyncDTO::class => 'send_notification',
                    ]
                ],
            ],
        );
    }
    ```
3. В файле `config/packages/messenger.yaml` добавляем секцию `framework.messenger.buses`
    ```yaml
    buses:
        messenger.bus.default:
            middleware:
                 - doctrine_ping_connection
                 - doctrine_close_connection
                 - doctrine_transaction
    ```
4. Добавляем класс `FeedBundle\Domain\MessageHandler\SendNotification\Handler`
    ```php
    <?php
    
    namespace FeedBundle\Domain\MessageHandler\SendNotification;
    
    use FeedBundle\Domain\DTO\SendNotificationAsyncDTO;
    use FeedBundle\Domain\DTO\SendNotificationDTO;
    use Symfony\Component\Messenger\Attribute\AsMessageHandler;
    use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
    use Symfony\Component\Messenger\Envelope;
    use Symfony\Component\Messenger\MessageBusInterface;
    
    #[AsMessageHandler]
    class Handler
    {
        public function __construct(private readonly MessageBusInterface $messageBus)
        {
        }
    
        public function __invoke(SendNotificationDTO $message): void
        {
            $envelope = new Envelope(
                new SendNotificationAsyncDTO($message->userId, $message->text, $message->channel),
                [new AmqpStamp($message->channel)]
            );
            $this->messageBus->dispatch($envelope);
        }
    }
    ```
5. В файл `supervisor/consumer.conf` добавляем новый процесс
    ```ini
    [program:messenger_doctrine]
    command=php /app/bin/console messenger:consume doctrine --limit=1000 --env=dev -vv
    process_name=messenger_doctrine_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/app/var/log/supervisor.messenger_doctrine.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/app/var/log/supervisor.messenger_doctrine.error.log
    stderr_capture_maxbytes=1MB
    ```
6. Перезапускаем контейнер supervisor командой `docker-compose restart supervisor`
7. Видим, что очередь в БД разобралась, и сообщения ушли в RabbitMQ и там обработались. 
