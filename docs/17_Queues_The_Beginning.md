# Очереди: начало

Запускаем контейнеры командой `docker-compose up -d`

## Установка RabbitMQ и rabbitmq-bundle

1. Добавляем в файл `docker\Dockerfile` установку расширения `pcntl` (в команду `docker-php-ext-install`)
2. В файл `docker-compose.yml` добавляем новый сервис:
    ```yaml
    rabbitmq:
      image: rabbitmq:3-management
      working_dir: /app
      hostname: rabbit-mq
      container_name: 'rabbit-mq'
      ports:
        - 15672:15672
        - 5672:5672
      environment:
        RABBITMQ_DEFAULT_USER: user
        RABBITMQ_DEFAULT_PASS: password
    ```
3. Запускаем контейнеры с пересборкой командой `docker-compose up -d --build`
4. Заходим в контейнер командой `docker exec -it php sh` и устанавливаем пакет `php-amqplib/rabbitmq-bundle`, дальнейшие
   команды выполняются из контейнера
5. Добавляем параметры для подключения к RabbitMQ в файл `.env`
    ```shell
    RABBITMQ_URL=amqp://user:password@rabbit-mq:5672
    RABBITMQ_VHOST=/
    ```
6. Заходим по адресу `localhost:15672` и авторизуемся с указанными реквизитами

## Добавляем функционал для асинхронной обработки

1. В классе `App\Entity\Subscription` добавляем атрибут `ORM\HasLifecycleCallbacks` для класса и атрибуты для методов
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
2. В классе `App\Domain\Service\SubscriptionService` исправляем метод `addSubscription`
    ```php
    public function addSubscription(User $author, User $follower): void
    {
        $subscription = new Subscription();
        $subscription->setAuthor($author);
        $subscription->setFollower($follower);
        $author->addSubscriptionFollower($subscription);
        $follower->addSubscriptionAuthor($subscription);
        $this->subscriptionRepository->create($subscription);
    }
    ```
3. Добавляем класс `App\Service\FollowerService`
    ```php
    <?php
    
    namespace App\Domain\Service;
    
    use App\Domain\Entity\User;
    use App\Domain\Model\CreateUserModel;
    use App\Domain\ValueObject\CommunicationChannelEnum;
    
    class FollowerService
    {
        public function __construct(
            private readonly UserService $userService,
            private readonly SubscriptionService $subscriptionService,
        ) {
            
        }
        
        public function addFollowers(User $user, string $followerLoginPrefix, int $count): int
        {
            $createdFollowers = 0;
            for ($i = 0; $i < $count; $i++) {
                $login = "{$followerLoginPrefix}_$i";
                $channel = random_int(0, 2) === 1 ? CommunicationChannelEnum::Email : CommunicationChannelEnum::Phone;
                $model = new CreateUserModel(
                    $login,
                    match ($channel) {
                        CommunicationChannelEnum::Email => "{$login}@mail.ru",
                        CommunicationChannelEnum::Phone => '+'.str_pad((string)abs(crc32($login)), 10, '0'),
                    },
                    $channel,
                    "{$login}_password",
                );
                $follower = $this->userService->create($model);
                $this->subscriptionService->addSubscription($user, $follower);
                $createdFollowers++;
            }
    
            return $createdFollowers;
        }
    }
    ```
4. Добавляем класс `App\Controller\Web\AddFollowers\v1\Input\AddFollowersDTO`
    ```php
    <?php
    
    namespace App\Controller\Web\AddFollowers\v1\Input;
    
    class AddFollowersDTO
    {
        public function __construct(
           public readonly int $authorId,
           public readonly string $followerLoginPrefix,
           public readonly int $count, 
        ) {
        }
    }
    ```
5. Добавляем класс `App\Controller\Web\AddFollowers\v1\Manager`
    ```php
    <?php
    
    namespace App\Controller\Web\AddFollowers\v1;
    
    use App\Controller\Web\AddFollowers\v1\Input\AddFollowersDTO;
    use App\Domain\Entity\User;
    use App\Domain\Service\FollowerService;
    
    class Manager
    {
        public function __construct(private readonly FollowerService $followerService)
        {
        }
        
        public function addFollowers(User $author, AddFollowersDTO $addFollowersDTO): int
        {
            return $this->followerService->addFollowers($author, $addFollowersDTO->followerLoginPrefix, $addFollowersDTO->count);
        }
    }
    ```
6. Добавляем класс `App\Controller\Web\AddFollowers\v1\Controller`
    ```php
    <?php
    
    namespace App\Controller\Web\AddFollowers\v1;
    
    use App\Controller\Web\AddFollowers\v1\Input\AddFollowersDTO;
    use App\Domain\Entity\User;
    use Symfony\Bridge\Doctrine\Attribute\MapEntity;
    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\HttpKernel\Attribute\AsController;
    use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
    use Symfony\Component\Routing\Attribute\Route;
    
    #[AsController]
    class Controller
    {
        public function __construct(
            private readonly Manager $manager,
        ) {
        }
    
        #[Route(path: 'api/v1/add-followers/{id}', requirements: ['id' => '\d+'], methods: ['POST'])]
        public function __invoke(#[MapEntity(id: 'id')] User $author, #[MapRequestPayload] AddFollowersDTO $addFollowersDTO): Response
        {
            return new JsonResponse(['count' => $this->manager->addFollowers($author, $addFollowersDTO)]);
        }
    }
    ```
7. Сбрасываем кэш метаданных Doctrine командой `php bin/console doctrine:cache:clear-metadata`
8. Выполняем запрос Add user v2 из Postman-коллекции v8 для добавления автора.
9. Выполняем запрос Add followers из Postman-коллекции v8, чтобы добавить этому автору 30 подписчиков.

## Переходим на асинхронное взаимодействие

1. Удаляем директорию `src/Consumer`
2. В файл `config/packages/old_sound_rabbit_mq.yaml` добавляем описание продюсера и консьюмера
    ```yaml
    producers:
     add_followers:
       connection: default
       exchange_options: {name: 'old_sound_rabbit_mq.add_followers', type: direct}
    
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
       qos_options: {prefetch_size: 0, prefetch_count: 1, global: false}
    ```
3. Добавляем классс `App\Application\RabbitMq\AbstractConsumer`
    ```php
    <?php
    
    namespace App\Application\RabbitMq;
    
    use Doctrine\ORM\EntityManagerInterface;
    use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
    use PhpAmqpLib\Message\AMQPMessage;
    use Symfony\Component\Serializer\Exception\UnsupportedFormatException;
    use Symfony\Component\Serializer\SerializerInterface;
    use Symfony\Component\Validator\Validator\ValidatorInterface;
    use Symfony\Contracts\Service\Attribute\Required;
    
    abstract class AbstractConsumer implements ConsumerInterface
    {
        private readonly EntityManagerInterface $entityManager;
        private readonly ValidatorInterface $validator;
        private readonly SerializerInterface $serializer;
    
        abstract protected function getMessageClass(): string;
    
        abstract protected function handle($message): int;
        
        #[Required]
        public function setEntityManager(EntityManagerInterface $entityManager): void
        {
            $this->entityManager = $entityManager;
        }
    
        #[Required]
        public function setValidator(ValidatorInterface $validator): void
        {
            $this->validator = $validator;
        }
    
        #[Required]
        public function setSerializer(SerializerInterface $serializer): void
        {
            $this->serializer = $serializer;
        }
    
        public function execute(AMQPMessage $msg): int
        {
            try {
                $message = $this->serializer->deserialize($msg->getBody(), $this->getMessageClass(), 'json');
                $errors = $this->validator->validate($message);
                if ($errors->count() > 0) {
                    return $this->reject((string)$errors);
                }
    
                return $this->handle($message);
            } catch (UnsupportedFormatException $e) {
                return $this->reject($e->getMessage());
            } finally {
                $this->entityManager->clear();
                $this->entityManager->getConnection()->close();
            }
        }
    
        protected function reject(string $error): int
        {
            echo "Incorrect message: $error";
    
            return self::MSG_REJECT;
        }
    }
    ```
4. Добавляем класс `App\Controller\Amqp\AddFollowers\Input\Message`
   ```php
   <?php
    
   namespace App\Controller\Amqp\AddFollowers\Input;
    
   use Symfony\Component\Validator\Constraints as Assert;
    
   class Message
   {
       public function __construct(
           #[Assert\Type('numeric')]
           public readonly int $userId,
           #[Assert\Type('string')]
           #[Assert\Length(max: 32)]
           public readonly string $followerLogin,
           #[Assert\Type('numeric')]
           public readonly int $count,
       ) {
       }
   }
   ```
5. Добавляем класс `App\Controller\Amqp\AddFollowers\Consumer`
    ```php
    <?php
    
    namespace App\Controller\Amqp\AddFollowers;
    
    use App\Application\RabbitMq\AbstractConsumer;
    use App\Controller\Amqp\AddFollowers\Input\Message;
    use App\Domain\Entity\User;
    use App\Domain\Service\FollowerService;
    use App\Domain\Service\UserService;
    
    class Consumer extends AbstractConsumer
    {
        public function __construct(
            private readonly UserService $userService,
            private readonly FollowerService $followerService,
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
            $user = $this->userService->findUserById($message->userId);
            if (!($user instanceof User)) {
                return $this->reject(sprintf('User ID %s was not found', $message->userId));
            }
    
            $this->followerService->addFollowersSync($user, $message->followerLogin, $message->count);
            
            return self::MSG_ACK;
        }
    }
    ```
6. Добавляем перечисление `App\Infrastructure\Bus\AmqpQueueEnum`
    ```php
    <?php
    
    namespace App\Infrastructure\Bus;
    
    enum AmqpQueueEnum: string
    {
        case AddFollowers = 'add_followers';
    }
    ```
7. Добавляем класс `App\Infrastructure\Bus\RabbitMqBus`
    ```php
    <?php
    
    namespace App\Infrastructure\Bus;
    
    use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;
    use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
    use Symfony\Component\Serializer\SerializerInterface;
    
    class RabbitMqBus
    {
        /** @var array<string,ProducerInterface> */
        private array $producers;
    
        public function __construct(private readonly SerializerInterface $serializer)
        {
            $this->producers = [];
        }
    
        public function registerProducer(AmqpExchangeEnum $exchange, ProducerInterface $producer): void
        {
            $this->producers[$exchange->value] = $producer;
        }
    
        public function publishToExchange(AmqpExchangeEnum $exchange, $message, ?string $routingKey = null, ?array $additionalProperties = null): bool
        {
            $serializedMessage = $this->serializer->serialize($message, 'json', [AbstractObjectNormalizer::SKIP_NULL_VALUES => true]);
            if (isset($this->producers[$exchange->value])) {
                $this->producers[$exchange->value]->publish($serializedMessage, $routingKey ?? '', $additionalProperties ?? []);
    
                return true;
            }
    
            return false;
        }
    }
    ```
8. Добавляем класс `App\Domain\DTO\AddFollowersDTO`
    ```php
    <?php
    
    namespace App\Domain\DTO;
    
    class AddFollowersMessage
    {
        public function __construct(
            public readonly int $userId,
            public readonly string $followerLogin,
            public readonly int $count
        ) {
        }
    }
     ```
9. Исправляем класс `App\Controller\Web\AddFollowers\v1\Input\AddFollowersDTO`
    ```php
    <?php
    
    namespace App\Controller\Web\AddFollowers\v1\Input;
    
    class AddFollowersDTO
    {
        public function __construct(
            public readonly string $followerLoginPrefix,
            public readonly int $count,
            public readonly bool $async = false,
        ) {
        }
    }    
    ```
10. Добавляем интерфейс `App\Domain\Bus\AddFollowersBusInterface`
    ```php
    <?php
    
    namespace App\Domain\Bus;
    
    use App\Domain\DTO\AddFollowersDTO;
    
    interface AddFollowersBusInterface
    {
        public function sendAddFollowersMessage(AddFollowersDTO $addFollowersDTO);
    }
    ```
11. Добавляем класс `App\Infrastructure\Bus\Adapter\AddFollowersRabbitMqBus`
12. Исправляем класс `App\Domain\Service\FollowerService`
    ```php
    <?php
    
    namespace App\Infrastructure\Bus\Adapter;
    
    use App\Domain\Bus\AddFollowersBusInterface;
    use App\Domain\DTO\AddFollowersDTO;
    use App\Infrastructure\Bus\AmqpExchangeEnum;
    use App\Infrastructure\Bus\RabbitMqBus;
    
    class AddFollowersRabbitMqBus implements AddFollowersBusInterface
    {
        public function __construct(private readonly RabbitMqBus $rabbitMqBus)
        {
        }
    
        public function sendAddFollowersMessage(AddFollowersDTO $addFollowersDTO): bool
        {
            return $this->rabbitMqBus->publishToExchange(AmqpExchangeEnum::AddFollowers, $addFollowersDTO);
        }
    }
    ```
13. Исправляем класс `App\Domain\Service\FollowerService`
    ```php
    <?php
    
    namespace App\Domain\Service;
    
    use App\Domain\Bus\AddFollowersBusInterface;
    use App\Domain\DTO\AddFollowersDTO;
    use App\Domain\Entity\User;
    use App\Domain\Model\CreateUserModel;
    use App\Domain\ValueObject\CommunicationChannelEnum;
    
    class FollowerService
    {
        public function __construct(
            private readonly UserService $userService,
            private readonly SubscriptionService $subscriptionService,
            private readonly AddFollowersBusInterface $addFollowersBus,
        ) {
    
        }
    
        public function addFollowersSync(User $user, string $followerLoginPrefix, int $count): int
        {
            $createdFollowers = 0;
            for ($i = 0; $i < $count; $i++) {
                $login = "{$followerLoginPrefix}_$i";
                $channel = random_int(0, 2) === 1 ? CommunicationChannelEnum::Email : CommunicationChannelEnum::Phone;
                $model = new CreateUserModel(
                    $login,
                    match ($channel) {
                        CommunicationChannelEnum::Email => "{$login}@mail.ru",
                        CommunicationChannelEnum::Phone => '+'.str_pad((string)abs(crc32($login)), 10, '0'),
                    },
                    $channel,
                    "{$login}_password",
                );
                $follower = $this->userService->create($model);
                $this->subscriptionService->addSubscription($user, $follower);
                $createdFollowers++;
            }
    
            return $createdFollowers;
        }
    
        public function addFollowersAsync(AddFollowersDTO $addFollowersDTO): int
        {
            return $this->addFollowersBus->sendAddFollowersMessage($addFollowersDTO) ? $addFollowersDTO->count : 0;
        }
    }
    ```
14. Исправляем класс `App\Controller\Web\AddFollowers\v1\Manager`
    ```php
    <?php
    
    namespace App\Controller\Web\AddFollowers\v1;
    
    use App\Controller\Web\AddFollowers\v1\Input\AddFollowersDTO;
    use App\Domain\DTO\AddFollowersDTO as InternalAddFollowersDTO;
    use App\Domain\Entity\User;
    use App\Domain\Service\FollowerService;
    
    class Manager
    {
        public function __construct(private readonly FollowerService $followerService)
        {
        }
    
        public function addFollowers(User $author, AddFollowersDTO $addFollowersDTO): int
        {
            return $addFollowersDTO->async ?
                $this->followerService->addFollowersAsync(
                    new InternalAddFollowersDTO(
                        $author->getId(),
                        $addFollowersDTO->followerLoginPrefix,
                        $addFollowersDTO->count
                    )
                ) :
                $this->followerService->addFollowersSync(
                    $author,
                    $addFollowersDTO->followerLoginPrefix,
                    $addFollowersDTO->count
                ); 
        }
    }
    ```
15. В файл `config/services.yaml` добавляем новый сервис
     ```yaml
     App\Infrastructure\Bus\RabbitMqBus:
         calls:
             - [ 'registerProducer', [ !php/enum App\Infrastructure\Bus\AmqpExchangeEnum::AddFollowers, '@old_sound_rabbit_mq.add_followers_producer' ] ]
     ```
16. Запускаем консьюмер командой `php bin/console rabbitmq:consumer add_followers -m 100`
17. Выполняем запрос Add followers из Postman-коллекции v8 с параметром `async` = 1, видим в интерфейсе RabbitMQ
    пришедшее сообщение и то, что в БД добавились подписчики

## Эмулируем многократную доставку

1. В классе `App\Controller\Amqp\AddFollowers\Consumer` в методе `handle` безусловно выбрасываем исключение перед
   последней строкой
    ```php
    throw new \RuntimeException('Something happens');
    ```
2. Перезапускаем консьюмер командой `php bin/console rabbitmq:consumer add_followers -m 100`
3. Выполняем запрос Add followers из Postman-коллекции v8 с параметром `async` = 1, видим в интерфейсе RabbitMQ
   пришедшее сообщение и то, что оно не обработалось, хотя в БД добавились подписчики
4. В консоли видим сообщение об ошибке от консьюмера, перезапускаем его командой
   `php bin/console rabbitmq:consumer add_followers -m 100` и видим ошибку уже добавления в БД из-за нарушения
   уникальности логина
   
## Исправляем проблему многократной доставки

1. В классе `App\Application\RabbitMq\AbstractConsumer` исправляем метод `execute`
    ```php
    <?php
    
    namespace App\Application\RabbitMq;
    
    use Doctrine\ORM\EntityManagerInterface;
    use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
    use PhpAmqpLib\Message\AMQPMessage;
    use Symfony\Component\Serializer\SerializerInterface;
    use Symfony\Component\Validator\Validator\ValidatorInterface;
    use Symfony\Contracts\Service\Attribute\Required;
    use Throwable;
    
    abstract class AbstractConsumer implements ConsumerInterface
    {
        private readonly EntityManagerInterface $entityManager;
        private readonly ValidatorInterface $validator;
        private readonly SerializerInterface $serializer;
    
        abstract protected function getMessageClass(): string;
    
        abstract protected function handle($message): int;
    
        #[Required]
        public function setEntityManager(EntityManagerInterface $entityManager): void
        {
            $this->entityManager = $entityManager;
        }
    
        #[Required]
        public function setValidator(ValidatorInterface $validator): void
        {
            $this->validator = $validator;
        }
    
        #[Required]
        public function setSerializer(SerializerInterface $serializer): void
        {
            $this->serializer = $serializer;
        }
    
        public function execute(AMQPMessage $msg): int
        {
            try {
                $message = $this->serializer->deserialize($msg->getBody(), $this->getMessageClass(), 'json');
                $errors = $this->validator->validate($message);
                if ($errors->count() > 0) {
                    return $this->reject((string)$errors);
                }
    
                return $this->handle($message);
            } catch (Throwable $e) {
                return $this->reject($e->getMessage());
            } finally {
                $this->entityManager->clear();
                $this->entityManager->getConnection()->close();
            }
        }
    
        protected function reject(string $error): int
        {
            echo "Incorrect message: $error";
    
            return self::MSG_REJECT;
        }
    }
    ```
2. Перезапускаем консьюмер из контейнера командой `php bin/console rabbitmq:consumer add_followers -m 100`
3. Видим сообщение об ошибке, но в интерфейсе RabbitMQ сообщение из очереди уходит
4. Останавливаем консьюмер в контейнере

## Эмулируем "убийственную" задачу

1. В классе `App\Controller\Amqp\AddFollowers\Consumer` исправляем метод `handle`
    ```php
    protected function handle($message): int
    {
        $user = $this->userService->findUserById($message->userId);
        if (!($user instanceof User)) {
            return $this->reject(sprintf('User ID %s was not found', $message->userId));
        }
        
        if ($message->count === 5) {
            sleep(1000);
        }

        $this->followerService->addFollowersSync($user, $message->followerLogin, $message->count);

        return self::MSG_ACK;
    }
    ```
2. Выполняем несколько запрос Add followers из Postman-коллекции v8 с параметром `async` = 1 и разными значениями
   `count`: сначала не равными 5, потом 5, потом ещё каким-нибудь не равные 5.
3. Перезапускаем консьюмер из контейнера командой `php bin/console rabbitmq:consumer add_followers -m 100`
4. Видим, что до "убийственной" задачи сообщения разобрались, но затем всё остановилось.
5. Останавливаем консьюмер и запускаем два параллельных консьюмера командой
    ```shell
    php bin/console rabbitmq:consumer add_followers -m 100 &
    php bin/console rabbitmq:consumer add_followers -m 100 &
    ```
6. Видим, что разобрались все сообщения, кроме "убийственной" задачи
7. Останавливаем консьюмеры командой `kill` c PID процессов консьюмеров и делаем Purge messages из очереди

## Работа с большим количеством сообщений за раз

1. В класс `App\Infrastructure\Bus\RabbitMqBus` добавляем метод `publishMultipleToExchange`
    ```php
    public function publishMultipleToExchange(AmqpExchangeEnum $exchange, array $messages, ?string $routingKey = null, ?array $additionalProperties = null): bool
    {
        $sentCount = 0;
        foreach ($messages as $message) {
            if ($this->publishToExchange($exchange, $message, $routingKey, $additionalProperties)) {
                $sentCount++;
            }
        }

        return $sentCount;
    }
    ```
2. В классе `App\Infrastructure\Bus\Adapter\AddFollowersRabbitMqBus` исправляем метод `sendAddFollowersMessage`
    ```php
    public function sendAddFollowersMessage(AddFollowersDTO $addFollowersDTO): bool
    {
        $messages = [];
        for ($i = 0; $i < $addFollowersDTO->count; $i++) {
            $messages[] = new AddFollowersDTO($addFollowersDTO->userId, $addFollowersDTO->followerLogin."_$i", 1);
        }

        return $this->rabbitMqBus->publishMultipleToExchange(AmqpExchangeEnum::AddFollowers, $messages);
    }
    ```
3. В классе `App\Controller\Amqp\AddFollowers\Consumer` исправляем метод `handle`
    ```php
    protected function handle($message): int
    {
        $user = $this->userService->findUserById($message->userId);
        if (!($user instanceof User)) {
            return $this->reject(sprintf('User ID %s was not found', $message->userId));
        }

        $this->followerService->addFollowersSync($user, $message->followerLogin, $message->count);
        sleep(1);

        return self::MSG_ACK;
    }
    ```
4. В файле `config/packages/old_sound_rabbit_mq.yaml` исправляем значение параметра `consumers.add_followers.qos_options`
    ```yaml
    qos_options: {prefetch_size: 0, prefetch_count: 30, global: false}
    ```
5. Выполняем запрос Add followers из Postman-коллекции v8 с параметром `async` = 1 и значением `count` = 100, видим 
   полученные сообщения в интерфейсе RabbitMQ
6. Запускаем два параллельных консьюмера командой
    ```shell
    php bin/console rabbitmq:consumer add_followers -m 100 &
    php bin/console rabbitmq:consumer add_followers -m 100 &
    ```
7. Видим в БД, что консьюмеры забирают по 30 сообщений и обрабатывают их параллельно, т.е. порядок обработки нарушен
8. Останавливаем консьюмеры командой `kill` c PID процессов консьюмеров

## Эмулируем ошибку при работе с prefetch

1. В классе `App\Controller\Amqp\AddFollowers\Consumer` исправляем метод `handle`
    ```php
    protected function handle($message): int
    {
        $user = $this->userService->findUserById($message->userId);
        if (!($user instanceof User)) {
            return $this->reject(sprintf('User ID %s was not found', $message->userId));
        }

        if ($message->followerLogin === 'multi_follower_error_11') {
            die();
        }
        
        $this->followerService->addFollowersSync($user, $message->followerLogin, $message->count);
        sleep(1);

        return self::MSG_ACK;
    }
    ```
2. Выполняем запрос Add followers из Postman-коллекции v8 с параметрами `async` = 1, `followersLogin` =
   `multi_follower_error` и `count` = 100, видим полученные сообщения в интерфейсе RabbitMQ
3. Запускаем два параллельных консьюмера командой
    ```shell
    php bin/console rabbitmq:consumer add_followers -m 100 &
    php bin/console rabbitmq:consumer add_followers -m 100 &
    ```
4. Видим в БД, что после падения одного из консьюмеров порядок обработки вторым становится совсем нелогичным, и затем
   он тоже падает на том же сообщении, которое вернулось в очередь
5. Делаем Purge messages из очереди
6. В классе `App\Consumer\AddFollowers\Consumer` в методе `execute` изменяем проверяемый логин на
   `multi_follower_error2_11`
7. Выполняем запрос Add followers из Postman-коллекции v8 с параметрами `async` = 1, `followersLogin` =
   `multi_follower_error2` и `count` = 100, видим полученные сообщения в интерфейсе RabbitMQ
8. Запускаем консьюмер командой `php bin/console rabbitmq:consumer add_followers -m 100`
9. После падения перезапускаем консьюмер и видим, что он сразу же падает
