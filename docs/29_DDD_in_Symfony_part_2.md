# Symfony Messenger

Запускаем контейнеры командой `docker-compose up -d`

## Добавляем синхронную команду

1. Добавляем интерфейс `App\Domain\Repository\UserRepositoryInterface`
    ```php
    <?php
    
    namespace App\Domain\Repository;
    
    use App\Domain\Entity\User;
    
    interface UserRepositoryInterface
    {
        public function create(User $user): int;
    }
    ```
2. Имплементируем добавленный интерфейс в классе `App\Infrastructure\Repository\UserRepository`
3. Добавляем класс `App\Domain\Command\CreateUser\Command`
    ```php
    <?php
    
    namespace App\Domain\Command\CreateUser;
    
    use App\Domain\Model\CreateUserModel;
    
    class Command
    {
        public function __construct(
            public readonly CreateUserModel $createUserModel,
        ) {
        }
    }
    ```
4. Добавляем класс `App\Domain\Command\CreateUser\Handler`
    ```php
    <?php
    
    namespace App\Domain\Command\CreateUser;
    
    use App\Domain\Entity\EmailUser;
    use App\Domain\Entity\PhoneUser;
    use App\Domain\Event\UserIsCreatedEvent;
    use App\Domain\Repository\UserRepositoryInterface;
    use App\Domain\ValueObject\CommunicationChannelEnum;
    use App\Domain\ValueObject\UserLogin;
    use Psr\EventDispatcher\EventDispatcherInterface;
    use Symfony\Component\Messenger\Attribute\AsMessageHandler;
    use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
    
    #[AsMessageHandler]
    class Handler
    {
        public function __construct(
            private readonly UserPasswordHasherInterface $userPasswordHasher,
            private readonly UserRepositoryInterface $userRepository,
            private readonly EventDispatcherInterface $eventDispatcher,
        ) {
        }
    
        public function __invoke(Command $command): int
        {
            $user = match($command->createUserModel->communicationChannel) {
                CommunicationChannelEnum::Email => (new EmailUser())->setEmail($command->createUserModel->communicationMethod),
                CommunicationChannelEnum::Phone => (new PhoneUser())->setPhone($command->createUserModel->communicationMethod),
            };
            $user->setLogin(UserLogin::fromString($command->createUserModel->login));
            $user->setPassword($this->userPasswordHasher->hashPassword($user, $command->createUserModel->password));
            $user->setAge($command->createUserModel->age);
            $user->setIsActive($command->createUserModel->isActive);
            $user->setRoles($command->createUserModel->roles);
            $this->userRepository->create($user);
            $this->eventDispatcher->dispatch(new UserIsCreatedEvent($user->getId(), $user->getLogin()));
        
            return $user->getId();
        }
    }
     ```
5. В файле `config/packages/messenger.yaml` исправляем секцию `framework.messenger.routing`
    ```yaml
    App\DTO\AddFollowersDTO: add_followers
    App\Domain\Command\CreateUser\Command: sync
    ```
6. Исправляем класс `App\Controller\Web\CreateUser\v2\Manager`
    ```php
    <?php
    
    namespace App\Controller\Web\CreateUser\v2;
    
    use App\Controller\Web\CreateUser\v2\Input\CreateUserDTO;
    use App\Controller\Web\CreateUser\v2\Output\CreatedUserDTO;
    use App\Domain\Command\CreateUser\Command;
    use App\Domain\Entity\EmailUser;
    use App\Domain\Entity\PhoneUser;
    use App\Domain\Entity\User;
    use App\Domain\Model\CreateUserModel;
    use App\Domain\Service\ModelFactory;
    use App\Domain\Service\UserService;
    use App\Domain\ValueObject\CommunicationChannelEnum;
    use Symfony\Component\Messenger\MessageBusInterface;
    use Symfony\Component\Messenger\Stamp\HandledStamp;
    
    class Manager implements ManagerInterface
    {
        public function __construct(
            /** @var ModelFactory<CreateUserModel> */
            private readonly ModelFactory $modelFactory,
            private readonly UserService $userService,
            private readonly MessageBusInterface $messageBus,
        ) {
        }
    
        public function create(CreateUserDTO $createUserDTO): CreatedUserDTO
        {
            $communicationMethod = $createUserDTO->phone ?? $createUserDTO->email;
            $communicationChannel = $createUserDTO->phone === null ? CommunicationChannelEnum::Email : CommunicationChannelEnum::Phone;
            $createUserModel = $this->modelFactory->makeModel(
                CreateUserModel::class,
                $createUserDTO->login,
                $communicationMethod,
                $communicationChannel,
                $createUserDTO->password,
                $createUserDTO->age,
                $createUserDTO->isActive,
                $createUserDTO->roles
            );
            $envelope = $this->messageBus->dispatch(new Command($createUserModel));
            /** @var HandledStamp|null $handledStamp */
            $handledStamp = $envelope->last(HandledStamp::class);
            $user = $this->userService->findUserById($handledStamp?->getResult());

            return new CreatedUserDTO(
                $user->getId(),
                $user->getLogin()->getValue(),
                $user->getAvatarLink(),
                $user->getRoles(),
                $user->getCreatedAt()->format('Y-m-d H:i:s'),
                $user->getUpdatedAt()->format('Y-m-d H:i:s'),
                $user instanceof PhoneUser ? $user->getPhone() : null,
                $user instanceof EmailUser ? $user->getEmail() : null,
            );
        }
    }
    ```
7. Выполняем запрос Add user v2 из Postman-коллекции v10. Видим успешный ответ, проверяем, что запись в БД создалась.

### Делаем команду асинхронной

1. В файле `config/packages/messenger.yaml`
   1. Добавляем новый транспорт в секцию `framework.messenger.transports`
        ```yaml
        create_user:
            dsn: "%env(MESSENGER_AMQP_TRANSPORT_DSN)%"
            options:
                exchange:
                    name: 'old_sound_rabbit_mq.create_user'
                    type: direct
        ```
   2. Исправляем секцию `framework.messenger.routing`
        ```yaml
        App\DTO\AddFollowersDTO: add_followers
        App\Domain\Command\CreateUser\CreateUserCommand: create_user
        ```
2. В файл `supervisor/consumer.conf` добавляем новую секцию
    ```ini
    [program:create_user]
    command=php /app/bin/console messenger:consume create_user --limit=1000 --env=dev -vv
    process_name=create_user_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/app/var/log/supervisor.create_user.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/app/var/log/supervisor.create_user.error.log
    stderr_capture_maxbytes=1MB
    ```
3. Перезапускаем контейнер супервизора командой `docker-compose restart supervisor`
4. Выполняем запрос Add user v2 из Postman-коллекции v10. Видим ошибку, но запись в БД создалась.

## Переходим на естественный идентификатор и добавляем ожидание

1. В классе `App\Domain\Command\Handler` исправляем метод `__invoke`
    ```php
    public function __invoke(Command $command): void
    {
        $user = match($command->createUserModel->communicationChannel) {
            CommunicationChannelEnum::Email => (new EmailUser())->setEmail($command->createUserModel->communicationMethod),
            CommunicationChannelEnum::Phone => (new PhoneUser())->setPhone($command->createUserModel->communicationMethod),
        };
        $user->setLogin(UserLogin::fromString($command->createUserModel->login));
        $user->setPassword($this->userPasswordHasher->hashPassword($user, $command->createUserModel->password));
        $user->setAge($command->createUserModel->age);
        $user->setIsActive($command->createUserModel->isActive);
        $user->setRoles($command->createUserModel->roles);
        $this->userRepository->create($user);
        $this->eventDispatcher->dispatch(new UserIsCreatedEvent($user->getId(), $user->getLogin()));
    }
    ```
2. Исправляем класс `App\Controller\Web\CreateUser\v2\Manager`
    ```php
    <?php
    
    namespace App\Controller\Web\CreateUser\v2;
    
    use App\Controller\Web\CreateUser\v2\Input\CreateUserDTO;
    use App\Controller\Web\CreateUser\v2\Output\CreatedUserDTO;
    use App\Domain\Command\CreateUser\Command;
    use App\Domain\Entity\EmailUser;
    use App\Domain\Entity\PhoneUser;
    use App\Domain\Model\CreateUserModel;
    use App\Domain\Service\ModelFactory;
    use App\Domain\Service\UserService;
    use App\Domain\ValueObject\CommunicationChannelEnum;
    use Symfony\Component\Messenger\MessageBusInterface;
    
    class Manager implements ManagerInterface
    {
        private const MAX_RETRIES_COUNT = 10;
        private const WAIT_INTERVAL_MICROSECONDS = 1_000_000;
    
        public function __construct(
            /** @var ModelFactory<CreateUserModel> */
            private readonly ModelFactory $modelFactory,
            private readonly UserService $userService,
            private readonly MessageBusInterface $messageBus,
        ) {
        }
    
        public function create(CreateUserDTO $createUserDTO): CreatedUserDTO
        {
            $communicationMethod = $createUserDTO->phone ?? $createUserDTO->email;
            $communicationChannel = $createUserDTO->phone === null ? CommunicationChannelEnum::Email : CommunicationChannelEnum::Phone;
            $createUserModel = $this->modelFactory->makeModel(
                CreateUserModel::class,
                $createUserDTO->login,
                $communicationMethod,
                $communicationChannel,
                $createUserDTO->password,
                $createUserDTO->age,
                $createUserDTO->isActive,
                $createUserDTO->roles
            );
            $this->messageBus->dispatch(new Command($createUserModel));
    
            $retriesCount = 0;
            $users = [];
            while ($users === [] && $retriesCount < self::MAX_RETRIES_COUNT) {
                usleep(self::WAIT_INTERVAL_MICROSECONDS);
                $users = $this->userService->findUsersByLogin($createUserDTO->login);
                $retriesCount++;
            }
            $user = $users[0];
    
            return new CreatedUserDTO(
                $user->getId(),
                $user->getLogin()->getValue(),
                $user->getAvatarLink(),
                $user->getRoles(),
                $user->getCreatedAt()->format('Y-m-d H:i:s'),
                $user->getUpdatedAt()->format('Y-m-d H:i:s'),
                $user instanceof PhoneUser ? $user->getPhone() : null,
                $user instanceof EmailUser ? $user->getEmail() : null,
            );
        }
    }
    ```
3. В классе `App\Infrastructure\Repository\UserRepository` исправляем метод `findUsersByLogin`
    ```php
    /**
     * @return User[]
     */
    public function findUsersByLogin(string $name): array
    {
        return $this->entityManager->getRepository(User::class)->findBy(['login' => UserLogin::fromString($name)]);
    }
    ```
4. Выполняем запрос Add user v2 из Postman-коллекции v10. Видим успешный ответ.

## Добавляем шину запросов

1. Добавляем интерфейс `App\Application\Query\QueryInterface`
    ```php
    <?php
    
    namespace App\Application\Query;
    
    /**
     * @template T
     */
    interface QueryInterface
    {
    }
    ```
2. Добавляем интерфейс `App\Application\Query\QueryBusInterface`
    ```php
    <?php
    
    namespace App\Application\Query;
    
    interface QueryBusInterface
    {
        /**
         * @template T
         *
         * @param QueryInterface<T> $query
         *
         * @return T
         */
        public function query(QueryInterface $query);
    }
    ```
3. Добавляем класс `App\Application\Query\QueryBus`
    ```php
    <?php
    
    namespace App\Application\Query;
    
    use Symfony\Component\Messenger\MessageBusInterface;
    use Symfony\Component\Messenger\Stamp\HandledStamp;
    
    class QueryBus implements QueryBusInterface
    {
        public function __construct(
            private readonly MessageBusInterface $baseQueryBus
        ) {
        }
    
        /**
         * @return mixed
         */
        public function query(QueryInterface $query)
        {
            $envelope = $this->baseQueryBus->dispatch($query);
            /** @var HandledStamp|null $handledStamp */
            $handledStamp = $envelope->last(HandledStamp::class);
            
            return $handledStamp?->getResult();
        }
    }
    ```
4. Добавляем класс `App\Domain\Query\GetFeed\Query`
    ```php
    <?php
    
    namespace App\Domain\Query\GetFeed;
    
    use App\Application\Query\QueryInterface;
    
    /**
     * @implements QueryInterface<Result>
     */
    class Query implements QueryInterface
    {
        public function __construct(
            public readonly int $userId,
            public readonly int $count,
        ) {
        }
    }
    ```
5. Добавляем класс `App\Domain\Query\GetFeed\Result`
    ```php
    <?php
    
    namespace App\Domain\Query\GetFeed;
    
    class Result
    {
        public function __construct(
            public readonly array $tweets
        ) {
        }
    }
    ```
6. Добавляем класс `App\Domain\Query\GetFeed\Handler`
    ```php
    <?php
    
    namespace App\Domain\Query\GetFeed;
    
    use App\Domain\Repository\FeedRepositoryInterface;
    use Symfony\Component\Messenger\Attribute\AsMessageHandler;
    
    #[AsMessageHandler]
    class Handler
    {
        public function __construct(
            private readonly FeedRepositoryInterface $feedRepository,
        ) {
        }
    
        public function __invoke(Query $query): Result
        {
            return new Result(
                $this->feedRepository->ensureFeed($query->userId, $query->count),
            );
        }
    }
    ```
7. Исправляем класс `App\Controller\Web\GetFeed\v1\Manager`
    ```php
    <?php
    
    namespace App\Controller\Web\GetFeed\v1;
    
    use App\Application\Query\QueryBusInterface;
    use App\Controller\Web\GetFeed\v1\Output\Response;
    use App\Controller\Web\GetFeed\v1\Output\TweetDTO;
    use App\Domain\Entity\User;
    use App\Domain\Query\GetFeed\Query;
    use App\Domain\Query\GetFeed\Result;
    
    class Manager
    {
        private const DEFAULT_FEED_SIZE = 20;
    
        /**
         * @param QueryBusInterface<Result> $queryBus
         */
        public function __construct(private readonly QueryBusInterface $queryBus)
        {
        }
    
        public function getFeed(User $user, ?int $count = null): Response
        {
            return new Response(
                array_map(
                    static fn (array $tweetData): TweetDTO => new TweetDTO(...$tweetData),
                    $this->queryBus->query(new Query($user->getId(), $count ?? self::DEFAULT_FEED_SIZE))->tweets,
                )
            );
        }
    }
    ```
8. Выполняем запрос Add followers из Postman-коллекции v10, чтобы получить подписчиков.
9. Выполняем запрос Post tweet из Postman-коллекции v10, дожидаемся обновления лент.
10. Выполняем запрос Get feed из Postman-коллекции v10 для любого подписчика, видим ответ с твитом.

## Устанавливаем deptrac

1. Заходим в контейнер командой `docker exec -it php sh`. Дальнейшие команды выполняем из контейнера
2. Устанавливаем deptrac командой `composer require qossmic/deptrac-shim --dev`
3. Исправляем файл `deptrac.yaml`
    ```yaml
    parameters:
        paths:
            - ./src
            - ./feedBundle/src
        exclude_files:
            - '#.*test.*#'
        layers:
            - name: Controller
              collectors:
                  - type: className
                    regex: ^App\\Controller.*
            - name: Domain
              collectors:
                  - type: className
                    regex: ^App\\Domain\\.*
            - name: Infrastructure
              collectors:
                  - type: className
                    regex: ^App\\Infrastructure\\.*
            - name: Application
              collectors:
                  - type: className
                    regex: ^App\\Application\\.*
            - name: FeedBundle
              collectors:
                  - type: className
                    regex: ^FeedBundle\\.*
        ruleset:
          Application:
            - Domain
            - Controller
          Controller:
            - Application
            - Domain
          Domain:
            - Application
          FeedBundle:
          Infrastructure:
              - Application
              - Domain
    ```
4. Запускаем `deptrac` командой `vendor/bin/deptrac --clear-cache`, видим 33 ошибки

### Исправляем некоторые зависимости

1. Исправляем интерфейс `App\Domain\Repository\UserRepositoryInterface`
    ```php
    <?php
    
    namespace App\Domain\Repository;
    
    use App\Domain\Entity\User;
    use Doctrine\ORM\NonUniqueResultException;
    
    interface UserRepositoryInterface
    {
        public function create(User $user): int;
    
        public function subscribeUser(User $author, User $follower): void;
    
        /**
         * @return User[]
         */
        public function findUsersByLogin(string $name): array;
    
        /**
         * @return User[]
         */
        public function findUsersByLoginWithCriteria(string $login): array;
    
        public function find(int $userId): ?User;
    
        public function updateLogin(User $user, string $login): void;
    
        public function updateAvatarLink(User $user, string $avatarLink): void;
    
        public function findUsersByLoginWithQueryBuilder(string $login): array;
    
        public function updateUserLoginWithQueryBuilder(int $userId, string $login): void;
    
        /**
         * @throws \Doctrine\DBAL\Exception
         */
        public function updateUserLoginWithDBALQueryBuilder(int $userId, string $login): void;
    
        /**
         * @throws NonUniqueResultException
         */
        public function findUserWithTweetsWithQueryBuilder(int $userId): array;
    
        /**
         * @throws \Doctrine\DBAL\Exception
         */
        public function findUserWithTweetsWithDBALQueryBuilder(int $userId): array;
    
        public function remove(User $user): void;
    
        public function removeInFuture(User $user, DateInterval $dateInterval): void;
    
        /**
         * @return User[]
         */
        public function findUsersByLoginWithDeleted(string $name): array;
    
        /**
         * @return User[]
         */
        public function findAll(): array;
    
        public function updateUserToken(User $user): string;
    
        public function findUserByToken(string $token): ?User;
    
        public function clearUserToken(User $user): void;
    
        /**
         * @return User[]
         */
        public function findUsersByQuery(string $query, int $perPage, int $page): array;
    }
    ```
2. В классах `App\Domain\Service\UserService` и `App\Domain\Service\SubscriptionService` меняем зависимость с класса
   репозитория на интерфейс
3. Запускаем `deptrac` командой `vendor/bin/deptrac --clear-cache`, видим уже 29 ошибок

### Формируем baseline

1. Выполняем команду `vendor/bin/deptrac --formatter=baseline`
2. В файле `deptrac.yaml` добавляем импорт baseline
    ```yaml
    imports:
      - deptrac.baseline.yaml
    ```
3. Запускаем `deptrac` командой `vendor/bin/deptrac --clear-cache`, видим, что ошибок нет
