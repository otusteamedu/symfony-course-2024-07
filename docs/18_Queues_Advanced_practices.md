# Очереди: расширенные возможности

## Добавляем supervisor

1. Добавляем файл `docker\supervisor\Dockerfile`
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
            memcached \
            redis \
            event \
        && rm -rf /tmp/pear \
        && echo "extension=redis.so" > /usr/local/etc/php/conf.d/redis.ini \
        && echo "extension=event.so" > /usr/local/etc/php/conf.d/event.ini \
        && echo "extension=memcached.so" > /usr/local/etc/php/conf.d/memcached.ini
    
    RUN apk add supervisor && mkdir /var/log/supervisor
    ```
2. Добавляем файл `docker\supervisor\supervisord.conf`
    ```ini
    [supervisord]
    logfile=/var/log/supervisor/supervisord.log
    pidfile=/var/run/supervisord.pid
    nodaemon=true
    
    [include]
    files=/app/supervisor/*.conf
    ```
3. Добавляем в `docker-compose.yml` сервис
    ```yaml
    supervisor:
       build: docker/supervisor
       container_name: 'supervisor'
       volumes:
           - ./:/app
           - ./docker/supervisor/supervisord.conf:/etc/supervisor/supervisord.conf
       working_dir: /app
       command: ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]
    ```
4. Добавляем конфигурацию для запуска консьюмеров в файле `supervisor/consumer.conf`
    ```ini
    [program:add_followers]
    command=php -dmemory_limit=1G /app/bin/console rabbitmq:consumer -m 100 add_followers --env=dev -vv
    process_name=add_follower_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/app/var/log/supervisor.add_followers.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/app/var/log/supervisor.add_followers.error.log
    stderr_capture_maxbytes=1MB
    ```
5. Запускаем контейнеры командой `docker-compose up -d`
6. Проверяем в RabbitMQ, что консьюмер запущен
7. Выполняем запрос Add user v2 из Postman-коллекции v9
8. Выполняем запрос Add followers из Postman-коллекции v9 с параметрами `async` = 1 и `count` = 1000, проверяем, что
   подписчики добавились

## Добавляем функционал ленты и нотификаций

1. Добавляем класс `App\Domain\Entity\Feed`
    ```php
    <?php
    
    namespace App\Domain\Entity;
    
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
    
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'reader_id', referencedColumnName: 'id')]
        private User $reader;
    
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
    
        public function getReader(): User
        {
            return $this->reader;
        }
    
        public function setReader(User $reader): void
        {
            $this->reader = $reader;
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
2. Добавляем класс `App\Domain\Entity\EmailNotification`
    ```php
    <?php
    
    namespace App\Domain\Entity;
    
    use DateTime;
    use Doctrine\ORM\Mapping as ORM;
    
    #[ORM\Table(name: 'email_notification')]
    #[ORM\Entity]
    #[ORM\HasLifecycleCallbacks]
    class EmailNotification implements EntityInterface
    {
        #[ORM\Column(name: 'id', type: 'bigint', unique:true)]
        #[ORM\Id]
        #[ORM\GeneratedValue(strategy: 'IDENTITY')]
        private int $id;
    
        #[ORM\Column(type: 'string', length: 128, nullable: false)]
        private string $email;
    
        #[ORM\Column(type: 'string', length: 512, nullable: false)]
        private string $text;
    
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
    
        public function getEmail(): string
        {
            return $this->email;
        }
    
        public function setEmail(string $email): void
        {
            $this->email = $email;
        }
    
        public function getText(): string
        {
            return $this->text;
        }
    
        public function setText(string $text): void
        {
            $this->text = $text;
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
3. Добавляем класс `App\Domain\Entity\SmsNotification`
    ```php
    <?php
    
    namespace App\Domain\Entity;
    
    use DateTime;
    use Doctrine\ORM\Mapping as ORM;
    
    #[ORM\Table(name: 'sms_notification')]
    #[ORM\Entity]
    #[ORM\HasLifecycleCallbacks]
    class SmsNotification implements EntityInterface
    {
        #[ORM\Column(name: 'id', type: 'bigint', unique:true)]
        #[ORM\Id]
        #[ORM\GeneratedValue(strategy: 'IDENTITY')]
        private int $id;
    
        #[ORM\Column(type: 'string', length: 11, nullable: false)]
        private string $phone;
    
        #[ORM\Column(type: 'string', length: 60, nullable: false)]
        private string $text;
    
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
    
        public function getPhone(): string
        {
            return $this->phone;
        }
    
        public function setPhone(string $phone): void
        {
            $this->phone = $phone;
        }
    
        public function getText(): string
        {
            return $this->text;
        }
    
        public function setText(string $text): void
        {
            $this->text = $text;
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
4. Заходим в контейнер командой `docker exec -it php sh`. Дальнейшие команды выполняем из контейнера
5. Создаём миграцию и применяем её командами
    ```shell
    php bin/console doctrine:migrations:diff
    php bin/console doctrine:migrations:migrate
    ```
6. Исправляем класс `App\Domain\Model\TweetModel`
     ```php
     <?php
     
     namespace App\Domain\Model;
     
     use DateTime;
     
     class TweetModel
     {
         public function __construct(
             public readonly int $id,
             public readonly string $author,
             public readonly int $authorId,
             public readonly string $text,
             public readonly DateTime $createdAt,
         ) {
         }
     
         public function toFeed(): array
         {
             return [
                 'id' => $this->id,
                 'author' => $this->author,
                 'text' => $this->text,
                 'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
             ];
         }
     }
     ```
7. В классе `App\Infrastructure\Repository\TweetRepositoryCacheDecorator` добавляем заполнение нового поля в
   `TweetModel`
8. Исправляем перечисление `App\Infrastructure\Bus\AmqpExchangeEnum`
    ```php
    <?php
    
    namespace App\Infrastructure\Bus;
    
    enum AmqpExchangeEnum: string
    {
        case AddFollowers = 'add_followers';
        case PublishTweet = 'publish_tweet';
        case SendNotification = 'send_notification';
    }
    ```
9. Добавляем интерфейс `App\Domain\Bus\PublishTweetBusInterface`
    ```php
    <?php
    
    namespace App\Domain\Bus;
    
    use App\Domain\Model\TweetModel;
    
    interface PublishTweetBusInterface
    {
        public function sendPublishTweetMessage(TweetModel $tweetModel): bool;
    }
    ```
10. Добавляем класс `App\Infrastructure\Bus\Adapter\PublishTweetRabbitMqBus`
     ```php
     <?php
    
     namespace App\Infrastructure\Bus\Adapter;
    
     use App\Domain\Bus\PublishTweetBusInterface;
     use App\Domain\Model\TweetModel;
     use App\Infrastructure\Bus\AmqpExchangeEnum;
     use App\Infrastructure\Bus\RabbitMqBus;
    
     class PublishTweetRabbitMqBus implements PublishTweetBusInterface
     {
         public function __construct(private readonly RabbitMqBus $rabbitMqBus)
         {
         }
    
         public function sendPublishTweetMessage(TweetModel $tweetModel): bool
         {
             return $this->rabbitMqBus->publishToExchange(AmqpExchangeEnum::PublishTweet, $tweetModel);
         }
     }
     ```
11. В классе `App\Infrastructure\Repository\SubscriptionRepository` добавляем метод `findAllByAuthor`
    ```php
    /**
     * @return Subscription[]
     */
    public function findAllByAuthor(User $author): array
    {
        $subscriptionRepository = $this->entityManager->getRepository(Subscription::class);
        return $subscriptionRepository->findBy(['author' => $author]) ?? [];
    }
    ```
12. Добавляем класс `App\Infrastructure\Repository\FeedRepository`
     ```php
     <?php
    
     namespace App\Infrastructure\Repository;
    
     use App\Domain\Entity\Feed;
     use App\Domain\Entity\User;
     use App\Domain\Model\TweetModel;
    
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
13. Исправляем класс `App\Domain\Service\SubscriptionService`
     ```php
     <?php
    
     namespace App\Domain\Service;
    
     use App\Domain\Entity\Subscription;
     use App\Domain\Entity\User;
     use App\Infrastructure\Repository\SubscriptionRepository;
     use App\Infrastructure\Repository\UserRepository;
    
     class SubscriptionService
     {
         public function __construct(
             private readonly UserRepository $userRepository,
             private readonly SubscriptionRepository $subscriptionRepository,
         ) {
         }
    
         public function addSubscription(User $author, User $follower): void
         {
             $subscription = new Subscription();
             $subscription->setAuthor($author);
             $subscription->setFollower($follower);
             $author->addSubscriptionFollower($subscription);
             $follower->addSubscriptionAuthor($subscription);
             $this->subscriptionRepository->create($subscription);
         }
    
         /**
          * @return User[]
          */
         public function getFollowers(int $authorId): array
         {
             $subscriptions = $this->getSubscriptionsByAuthorId($authorId);
             $mapper = static function(Subscription $subscription) {
                 return $subscription->getFollower();
             };
    
             return array_map($mapper, $subscriptions);
         }
    
         /**
          * @return Subscription[]
          */
         private function getSubscriptionsByAuthorId(int $authorId): array
         {
             $author = $this->userRepository->find($authorId);
             if (!($author instanceof User)) {
                 return [];
             }
            
             return $this->subscriptionRepository->findAllByAuthor($author);
         }
     }
     ```
14. Добавляем класс `App\Domain\Service\FeedService`
    ```php
    <?php
    
    namespace App\Domain\Service;
    
    use App\Domain\Bus\PublishTweetBusInterface;
    use App\Domain\Entity\User;
    use App\Domain\Model\TweetModel;
    use App\Infrastructure\Repository\FeedRepository;
    
    class FeedService
    {
        public function __construct(
            private readonly FeedRepository $feedRepository,
            private readonly SubscriptionService $subscriptionService,
            private readonly PublishTweetBusInterface $publishTweetBus,
        ) {
        }
    
        public function ensureFeed(User $user, int $count): array
        {
            $feed = $this->feedRepository->ensureFeedForReader($user);
    
            return $feed === null ? [] : array_slice($feed->getTweets(), -$count);
        }
    
        public function spreadTweetAsync(TweetModel $tweet): void
        {
            $this->publishTweetBus->sendPublishTweetMessage($tweet);
        }
    
        public function spreadTweetSync(TweetModel $tweet): void
        {
            $followers = $this->subscriptionService->getFollowers($tweet->authorId);
    
            foreach ($followers as $follower) {
                $this->feedRepository->putTweetToReaderFeed($tweet, $follower);
            }
        }
    }
    ```
15. Исправляем класс `App\Domain\Service\TweetService`
    ```php
    <?php
    
    namespace App\Domain\Service;
    
    use App\Domain\Entity\Tweet;
    use App\Domain\Entity\User;
    use App\Domain\Model\TweetModel;
    use App\Domain\Repository\TweetRepositoryInterface;
    
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
            $tweetModel = new TweetModel(
                $tweet->getId(),
                $tweet->getAuthor()->getLogin(),
                $tweet->getAuthor()->getId(),
                $tweet->getText(),
                $tweet->getCreatedAt()
            );
            if ($async) {
                $this->feedService->spreadTweetAsync($tweetModel);
            } else {
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
16. Исправляем класс `App\Controller\Web\PostTweet\v1\PostTweetDTO`
    ```php
    <?php
    
    namespace App\Controller\Web\PostTweet\v1\Input;
    
    class PostTweetDTO
    {
        public function __construct(
            public readonly int $userId,
            public readonly string $text,
            public readonly bool $async = false,
        ) {
        }
    }
    ```
17. В классе `App\Controller\Web\PostTweet\v1\Manager` исправляем метод `postTweet`
    ```php
    public function postTweet(PostTweetDTO $tweetDTO): bool
    {
        $user = $this->userService->findUserById($tweetDTO->userId);

        if ($user === null) {
            return false;
        }

        $this->tweetService->postTweet($user, $tweetDTO->text, $tweetDTO->async);

        return true;
    }
    ```
18. Добавляем класс `App\Controller\Web\GetFeed\v1\Manager`
    ```php
    <?php
    
    namespace App\Controller\Web\GetFeed\v1;
    
    use App\Domain\Entity\User;
    use App\Domain\Service\FeedService;
    
    class Manager
    {
        private const DEFAULT_FEED_SIZE = 20;
        
        public function __construct(private readonly FeedService $feedService)
        {
        }
        
        public function getFeed(User $user, ?int $count = null): array
        {
            return $this->feedService->ensureFeed($user, $count ?? self::DEFAULT_FEED_SIZE);
        }
    }
    ```
19. Добавляем класс `App\Controller\Web\GetFeed\v1\Controller`
    ```php
    <?php
    
    namespace App\Controller\Web\GetFeed\v1;
    
    use App\Domain\Entity\User;
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
    
        #[Route(path: 'api/v1/get-feed/{id}', methods: ['GET'])]
        public function __invoke(#[MapEntity(id: 'id')]User $user, #[MapQueryParameter]?int $count = null): Response
        {
            return new JsonResponse(['tweets' => $this->manager->getFeed($user, $count)]);
        }
    }
    ```
20. В классе `App\Controller\Amqp\AddFollowers\Consumer` в методе `execute` убираем `sleep` и запланированную ошибку
21. Выполняем запрос Post tweet из Postman-коллекции v9 с параметром `async` = 0, проверяем, что ленты материализовались

## Добавляем консьюмеры

1. Добавляем класс `App\Infrastructure\Repository\EmailNotificationRepository`
    ```php
    <?php
    
    namespace App\Infrastructure\Repository;
    
    use App\Domain\Entity\EmailNotification;
    
    class EmailNotificationRepository extends AbstractRepository
    {
        public function create(EmailNotification $notification): int
        {
            return $this->store($notification);
        }
    }
    ```
2. Добавляем класс `App\Domain\Service\EmailNotificationService`
    ```php
    <?php
    
    namespace App\Domain\Service;
    
    use App\Domain\Entity\EmailNotification;
    use App\Infrastructure\Repository\EmailNotificationRepository;
    
    class EmailNotificationService
    {
        public function __construct(private readonly EmailNotificationRepository $emailNotificationRepository)
        {
        }
    
        public function saveEmailNotification(string $email, string $text): void {
            $emailNotification = new EmailNotification();
            $emailNotification->setEmail($email);
            $emailNotification->setText($text);
            $this->emailNotificationRepository->create($emailNotification);
        }
    }
    ```
3. Добавляем класс `App\Infrastructure\Repository\SmsNotificationRepository`
    ```php
    <?php
    
    namespace App\Infrastructure\Repository;
    
    use App\Domain\Entity\SmsNotification;
    
    class SmsNotificationRepository extends AbstractRepository
    {
        public function create(SmsNotification $notification): int
        {
            return $this->store($notification);
        }
    }
    ```
4. Добавляем класс `App\Domain\Service\PhoneNotificationService`
    ```php
    <?php
    
    namespace App\Domain\Service;
    
    use App\Domain\Entity\SmsNotification;
    use App\Infrastructure\Repository\SmsNotificationRepository;
    
    class SmsNotificationService
    {
        public function __construct(private readonly SmsNotificationRepository $emailNotificationRepository)
        {
        }
    
        public function saveSmsNotification(string $phone, string $text): void {
            $emailNotification = new SmsNotification();
            $emailNotification->setPhone($phone);
            $emailNotification->setText($text);
            $this->emailNotificationRepository->create($emailNotification);
        }
    }
    ```
5. Добавляем класс `App\Controller\Amqp\SendEmailNotification\Input\Message`
    ```php
    <?php
    
    namespace App\Controller\Amqp\SendEmailNotification\Input;
    
    use Symfony\Component\Validator\Constraints as Assert;
    
    class Message
    {
        public function __construct(
            #[Assert\Type('numeric')]
            public readonly int $userId,
            #[Assert\Type('string')]
            #[Assert\Length(max: 512)]
            public readonly string $text,
        ) {
        }
    }
    ```
6. Добавляем класс `App\Controller\Amqp\SendEmailNotification\Consumer`
    ```php
    <?php
    
    namespace App\Controller\Amqp\SendEmailNotification;
    
    use App\Application\RabbitMq\AbstractConsumer;
    use App\Controller\Amqp\SendEmailNotification\Input\Message;
    use App\Domain\Entity\EmailUser;
    use App\Domain\Service\EmailNotificationService;
    use App\Domain\Service\UserService;
    
    class Consumer extends AbstractConsumer
    {
        public function __construct(
            private readonly UserService $userService,
            private readonly EmailNotificationService $emailNotificationService,
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
            if (!($user instanceof EmailUser)) {
                return $this->reject(sprintf('User ID %s was not found or does not use email', $message->userId));
            }
            
            $this->emailNotificationService->saveEmailNotification($user->getEmail(), $message->text);
    
            return self::MSG_ACK;
        }
    }
    ```
7. Добавляем класс `App\Controller\Amqp\SendSmsNotification\Input\Message`
    ```php
    <?php
    
    namespace App\Controller\Amqp\SendSmsNotification\Input;
    
    use Symfony\Component\Validator\Constraints as Assert;
    
    class Message
    {
        public function __construct(
            #[Assert\Type('numeric')]
            public readonly int $userId,
            #[Assert\Type('string')]
            #[Assert\Length(max: 60)]
            public readonly string $text,
        ) {
        }
    }
    ```
8. Добавляем класс `App\Controller\Amqp\SendSmsNotification\Consumer`
    ```php
    <?php
    
    namespace App\Controller\Amqp\SendSmsNotification;
    
    use App\Application\RabbitMq\AbstractConsumer;
    use App\Controller\Amqp\SendSmsNotification\Input\Message;
    use App\Domain\Entity\PhoneUser;
    use App\Domain\Service\SmsNotificationService;
    use App\Domain\Service\UserService;
    
    class Consumer extends AbstractConsumer
    {
        public function __construct(
            private readonly UserService $userService,
            private readonly SmsNotificationService $emailNotificationService,
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
            if (!($user instanceof PhoneUser)) {
                return $this->reject(sprintf('User ID %s was not found or does not use phone', $message->userId));
            }
    
            $this->emailNotificationService->saveSmsNotification($user->getPhone(), $message->text);
    
            return self::MSG_ACK;
        }
    }
    ```
9. Добавляем класс `App\Domain\DTO\SendNotificationDTO`
    ```php
    <?php
    
    namespace App\Domain\DTO;
    
    use App\Domain\ValueObject\CommunicationChannelEnum;

    class SendNotificationDTO
    {
        public function __construct(
            public readonly int $userId,
            public readonly string $text,
            public readonly CommunicationChannelEnum $channel,
        ) {
        }
    }
    ```
10. Добавляем интерфейс `App\Domain\Bus\SendNotificationBusInterface`
    ```php
    <?php
    
    namespace App\Domain\Bus;
    
    use App\Domain\DTO\SendNotificationDTO;
    
    interface SendNotificationBusInterface
    {
        public function sendNotification(SendNotificationDTO $sendNotificationDTO): bool;
    }
    ```
11. Добавляем класс `App\Instrastructure\Bus\Adapter\SendNotificationRabbitMqBus`
    ```php
    <?php
    
    namespace App\Infrastructure\Bus\Adapter;
    
    use App\Domain\Bus\SendNotificationBusInterface;
    use App\Domain\DTO\SendNotificationDTO;
    use App\Infrastructure\Bus\AmqpExchangeEnum;
    use App\Infrastructure\Bus\RabbitMqBus;
    
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
                $sendNotificationDTO->channel->value
            );
        }
    }
    ```
12. Добавляем класс `App\Controller\Amqp\PublishTweet\Input\Message`
    ```php
    <?php
    
    namespace App\Controller\Amqp\PublishTweet\Input;
    
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
        ) {            
        }
    } 
    ```
13. Добавляем класс `App\Controller\Amqp\PublishTweet\Consumer`
    ```php
    <?php
    
    namespace App\Controller\Amqp\PublishTweet;
    
    use App\Application\RabbitMq\AbstractConsumer;
    use App\Controller\Amqp\PublishTweet\Input\Message;
    use App\Domain\Model\TweetModel;
    use App\Domain\Service\FeedService;
    
    class Consumer extends AbstractConsumer
    {
        public function __construct(
            private readonly FeedService $feedService,
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
            $this->feedService->spreadTweetSync($tweet);
    
            return self::MSG_ACK;
        }
    }
    ```
14. Исправляем класс `App\Domain\Service\FeedService`
    ```php
    <?php
    
    namespace App\Domain\Service;
    
    use App\Domain\Bus\PublishTweetBusInterface;
    use App\Domain\Bus\SendNotificationBusInterface;
    use App\Domain\DTO\SendNotificationDTO;
    use App\Domain\Entity\User;
    use App\Domain\Model\TweetModel;
    use App\Infrastructure\Repository\FeedRepository;
    
    class FeedService
    {
        public function __construct(
            private readonly FeedRepository $feedRepository,
            private readonly SubscriptionService $subscriptionService,
            private readonly PublishTweetBusInterface $publishTweetBus,
            private readonly SendNotificationBusInterface $sendNotificationBus,
        ) {
        }
    
        public function ensureFeed(User $user, int $count): array
        {
            $feed = $this->feedRepository->ensureFeedForReader($user);
    
            return $feed === null ? [] : array_slice($feed->getTweets(), -$count);
        }
    
        public function spreadTweetAsync(TweetModel $tweet): void
        {
            $this->publishTweetBus->sendPublishTweetMessage($tweet);
        }
    
        public function spreadTweetSync(TweetModel $tweet): void
        {
            $followers = $this->subscriptionService->getFollowers($tweet->authorId);
    
            foreach ($followers as $follower) {
                $this->feedRepository->putTweetToReaderFeed($tweet, $follower);
                $sendNotificationDTO = new SendNotificationDTO(
                    $follower->getId(),
                    $tweet->text,
                    $follower instanceof EmailUser ? CommunicationChannelEnum::Email : CommunicationChannelEnum::Phone
                );
                $this->sendNotificationBus->sendNotification($sendNotificationDTO);
            }
        }
    }
    ```
15. В файл `config/services.yaml` добавляем к сервису `App\Infrastructure\Bus\RabbitMqBus` регистрацию новых продюсеров:
     ```yaml
     - [ 'registerProducer', [ !php/enum App\Infrastructure\Bus\AmqpExchangeEnum::SendNotification, '@old_sound_rabbit_mq.send_notification_producer' ] ]
     - [ 'registerProducer', [ !php/enum App\Infrastructure\Bus\AmqpExchangeEnum::PublishTweet, '@old_sound_rabbit_mq.publish_tweet_producer' ] ]
     ```
16. Добавляем описание новых продюсеров и консьюмеров в файл `config/packages/old_sound_rabbit_mq.yaml`
     1. в секцию `producers`
         ```yaml
         publish_tweet:
             connection: default
             exchange_options: {name: 'old_sound_rabbit_mq.publish_tweet', type: direct}
         send_notification:
             connection: default
             exchange_options: {name: 'old_sound_rabbit_mq.send_notification', type: topic}
         ```
     2. в секцию `consumers`
         ```yaml
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
17. Исправляем файл `supervisor/consumer.conf`
    ```ini
    [program:add_followers]
    command=php -dmemory_limit=1G /app/bin/console rabbitmq:consumer -m 100 add_followers --env=dev -vv
    process_name=add_follower_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/app/var/log/supervisor.add_followers.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/app/var/log/supervisor.add_followers.error.log
    stderr_capture_maxbytes=1MB
   
    [program:publish_tweet]
    command=php -dmemory_limit=1G /app/bin/console rabbitmq:consumer -m 100 publish_tweet --env=dev -vv
    process_name=publish_tweet_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/app/var/log/supervisor.publish_tweet.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/app/var/log/supervisor.publish_tweet.error.log
    stderr_capture_maxbytes=1MB

    [program:send_notification_email]
    command=php -dmemory_limit=1G /app/bin/console rabbitmq:consumer -m 100 send_notification.email --env=dev -vv
    process_name=send_notification_email_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/app/var/log/supervisor.send_notification_email.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/app/var/log/supervisor.send_notification_email.error.log
    stderr_capture_maxbytes=1MB
   
    [program:send_notification_sms]
    command=php -dmemory_limit=1G /app/bin/console rabbitmq:consumer -m 100 send_notification.sms --env=dev -vv
    process_name=send_notification_sms_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/app/var/log/supervisor.send_notification_sms.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/app/var/log/supervisor.send_notification_sms.error.log
    stderr_capture_maxbytes=1MB
    ```
18. Перезапускаем контейнер супервизора командой `docker-compose restart supervisor`
19. Выполняем запрос Post tweet из Postman-коллекции v8 с параметром `async` = 1
20. Видим, что сообщения из точки обмена `old_sound_rabbit_mq.send_notification` распределились по двум очередям
    `old_sound_rabbit_mq.consumer.send_notification.email` и `old_sound_rabbit_mq.consumer.send_notification.sms`
   
## Добавляем согласованное хэширование

1. Входим в контейнер `rabbit-mq` командой `docker exec -it rabbit-mq sh` и выполняем в нём команду
    ```shell
    rabbitmq-plugins enable rabbitmq_consistent_hash_exchange
    ```
2. Исправляем класс `App\Domain\Service\FeedService`
    ```php
    <?php
    
    namespace App\Domain\Service;
    
    use App\Domain\Bus\PublishTweetBusInterface;
    use App\Domain\Bus\SendNotificationBusInterface;
    use App\Domain\DTO\SendNotificationDTO;
    use App\Domain\Entity\EmailUser;
    use App\Domain\Entity\User;
    use App\Domain\Model\TweetModel;
    use App\Domain\ValueObject\CommunicationChannelEnum;
    use App\Infrastructure\Repository\FeedRepository;
    
    class FeedService
    {
        public function __construct(
            private readonly FeedRepository $feedRepository,
            private readonly SubscriptionService $subscriptionService,
            private readonly PublishTweetBusInterface $publishTweetBus,
            private readonly SendNotificationBusInterface $sendNotificationBus,
        ) {
        }
    
        public function ensureFeed(User $user, int $count): array
        {
            $feed = $this->feedRepository->ensureFeedForReader($user);
    
            return $feed === null ? [] : array_slice($feed->getTweets(), -$count);
        }
    
        public function spreadTweetAsync(TweetModel $tweet): void
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
                $follower instanceof EmailUser ? CommunicationChannelEnum::Email : CommunicationChannelEnum::Phone
            );
            $this->sendNotificationBus->sendNotification($sendNotificationDTO);
        }
    }
    ```
3. Создаём класс `App\Controller\Amqp\UpdateFeed\Input\Message`
    ```php
    <?php
    
    namespace App\Controller\Amqp\UpdateFeed\Input;
    
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
        ) {
        }
    }
    ``` 
4. Создаём класс `App\Controller\Amqp\UpdateFeed\Consumer`
    ```php
    <?php
    
    namespace App\Controller\Amqp\UpdateFeed;
    
    use App\Application\RabbitMq\AbstractConsumer;
    use App\Controller\Amqp\UpdateFeed\Input\Message;
    use App\Domain\Entity\User;
    use App\Domain\Model\TweetModel;
    use App\Domain\Service\FeedService;
    use App\Domain\Service\UserService;
    
    class Consumer extends AbstractConsumer
    {
        public function __construct(
            private readonly FeedService $feedService,
            private readonly UserService $userService,
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
    
            return self::MSG_ACK;
        }
    }
    ```
5. Добавляем класс `App\Domain\DTO\UpdateFeedDTO`
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
        ) {
        }
    }
    ```
6. Добавляем интерфейс `App\Domain\Bus\UpdateFeedBusInterface`
    ```php
    <?php
    
    namespace App\Domain\Bus;
    
    use App\Domain\DTO\UpdateFeedDTO;
    
    interface UpdateFeedBusInterface
    {
        public function sendUpdateFeedMessage(UpdateFeedDTO $updateFeedDTO): bool;
    }
    ```
7. Добавляем класс `App\Infrastructure\Bus\Adapter\UpdateFeedRabbitMqBus`
    ```php
    <?php
    
    namespace App\Infrastructure\Bus\Adapter;
    
    use App\Domain\Bus\UpdateFeedBusInterface;
    use App\Domain\DTO\UpdateFeedDTO;
    use App\Infrastructure\Bus\AmqpExchangeEnum;
    use App\Infrastructure\Bus\RabbitMqBus;
    
    class UpdateFeedRabbitMqBus implements UpdateFeedBusInterface
    {
        public function __construct(private readonly RabbitMqBus $rabbitMqBus)
        {
        }
    
        public function sendUpdateFeedMessage(UpdateFeedDTO $updateFeedDTO): bool
        {
            return $this->rabbitMqBus->publishToExchange(
                AmqpExchangeEnum::UpdateFeed,
                $updateFeedDTO,
                (string)$updateFeedDTO->followerId
            );
        }
    }
    ```
8. Исправляем перечисление `App\Infrastructure\Bus\AmqpExchangeEnum`
    ```php
    <?php
    
    namespace App\Infrastructure\Bus;
    
    enum AmqpExchangeEnum: string
    {
        case AddFollowers = 'add_followers';
        case PublishTweet = 'publish_tweet';
        case SendNotification = 'send_notification';
        case UpdateFeed = 'update_feed';
    }
    ```
9. В файл `config/services.yaml` добавляем к сервису `App\Infrastructure\Bus\RabbitMqBus` регистрацию нового продюсера:
    ```yaml
    - [ 'registerProducer', [ !php/enum App\Infrastructure\Bus\AmqpExchangeEnum::UpdateFeed, '@old_sound_rabbit_mq.update_feed_producer' ] ]
    ```
10. Исправляем класс `App\Controller\Amqp\PublishTweet\Consumer`
    ```php
    <?php
   
    namespace App\Controller\Amqp\PublishTweet;
   
    use App\Application\RabbitMq\AbstractConsumer;
    use App\Controller\Amqp\PublishTweet\Input\Message;
    use App\Domain\Bus\UpdateFeedBusInterface;
    use App\Domain\DTO\UpdateFeedDTO;
    use App\Domain\Service\SubscriptionService;
   
    class Consumer extends AbstractConsumer
    {
        public function __construct(
            private readonly SubscriptionService $subscriptionService,
            private readonly UpdateFeedBusInterface $updateFeedBus,
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
            $followers = $this->subscriptionService->getFollowers($message->authorId);
            foreach ($followers as $follower) {
                $updateFeedDTO = new UpdateFeedDTO(
                    $message->id,
                    $message->author,
                    $message->authorId,
                    $message->text,
                    $message->createdAt,
                    $follower->getId(),
                );
                $this->updateFeedBus->sendUpdateFeedMessage($updateFeedDTO);
            }
   
            return self::MSG_ACK;
        }
    }
    ```
11. Добавляем описание нового продюсера и консьюмеров в файл `config/packages/old_sound_rabbit_mq.yaml`
     1. в секцию `producers`
         ```yaml
         update_feed:
             connection: default
             exchange_options: {name: 'old_sound_rabbit_mq.update_feed', type: x-consistent-hash}
         ```
     2. в секцию `consumers`
          ```yaml
          update_feed_0:
              connection: default
              exchange_options: {name: 'old_sound_rabbit_mq.update_feed', type: x-consistent-hash}
              queue_options: {name: 'old_sound_rabbit_mq.consumer.update_feed_0', routing_key: '1'}
              callback: App\Controller\Amqp\UpdateFeed\Consumer
              idle_timeout: 300
              idle_timeout_exit_code: 0
              graceful_max_execution:
                  timeout: 1800
                  exit_code: 0
              qos_options: {prefetch_size: 0, prefetch_count: 1, global: false}
          update_feed_1:
              connection: default
              exchange_options: {name: 'old_sound_rabbit_mq.update_feed', type: x-consistent-hash}
              queue_options: {name: 'old_sound_rabbit_mq.consumer.update_feed_1', routing_key: '1'}
              callback: App\Controller\Amqp\UpdateFeed\Consumer
              idle_timeout: 300
              idle_timeout_exit_code: 0
              graceful_max_execution:
                  timeout: 1800
                  exit_code: 0
              qos_options: {prefetch_size: 0, prefetch_count: 1, global: false}
          update_feed_2:
              connection: default
              exchange_options: {name: 'old_sound_rabbit_mq.update_feed', type: x-consistent-hash}
              queue_options: {name: 'old_sound_rabbit_mq.consumer.update_feed_2', routing_key: '1'}
              callback: App\Controller\Amqp\UpdateFeed\Consumer
              idle_timeout: 300
              idle_timeout_exit_code: 0
              graceful_max_execution:
                  timeout: 1800
                  exit_code: 0
              qos_options: {prefetch_size: 0, prefetch_count: 1, global: false}
          update_feed_3:
              connection: default
              exchange_options: {name: 'old_sound_rabbit_mq.update_feed', type: x-consistent-hash}
              queue_options: {name: 'old_sound_rabbit_mq.consumer.update_feed_3', routing_key: '1'}
              callback: App\Controller\Amqp\UpdateFeed\Consumer
              idle_timeout: 300
              idle_timeout_exit_code: 0
              graceful_max_execution:
                  timeout: 1800
                  exit_code: 0
              qos_options: {prefetch_size: 0, prefetch_count: 1, global: false}
          update_feed_4:
              connection: default
              exchange_options: {name: 'old_sound_rabbit_mq.update_feed', type: x-consistent-hash}
              queue_options: {name: 'old_sound_rabbit_mq.consumer.update_feed_4', routing_key: '1'}
              callback: App\Controller\Amqp\UpdateFeed\Consumer
              idle_timeout: 300
              idle_timeout_exit_code: 0
              graceful_max_execution:
                  timeout: 1800
                  exit_code: 0
              qos_options: {prefetch_size: 0, prefetch_count: 1, global: false}
          update_feed_5:
              connection: default
              exchange_options: {name: 'old_sound_rabbit_mq.update_feed', type: x-consistent-hash}
              queue_options: {name: 'old_sound_rabbit_mq.consumer.update_feed_5', routing_key: '1'}
              callback: App\Controller\Amqp\UpdateFeed\Consumer
              idle_timeout: 300
              idle_timeout_exit_code: 0
              graceful_max_execution:
                  timeout: 1800
                  exit_code: 0
              qos_options: {prefetch_size: 0, prefetch_count: 1, global: false}
          update_feed_6:
              connection: default
              exchange_options: {name: 'old_sound_rabbit_mq.update_feed', type: x-consistent-hash}
              queue_options: {name: 'old_sound_rabbit_mq.consumer.update_feed_6', routing_key: '1'}
              callback: App\Controller\Amqp\UpdateFeed\Consumer
              idle_timeout: 300
              idle_timeout_exit_code: 0
              graceful_max_execution:
                  timeout: 1800
                  exit_code: 0
              qos_options: {prefetch_size: 0, prefetch_count: 1, global: false}
          update_feed_7:
              connection: default
              exchange_options: {name: 'old_sound_rabbit_mq.update_feed', type: x-consistent-hash}
              queue_options: {name: 'old_sound_rabbit_mq.consumer.update_feed_7', routing_key: '1'}
              callback: App\Controller\Amqp\UpdateFeed\Consumer
              idle_timeout: 300
              idle_timeout_exit_code: 0
              graceful_max_execution:
                  timeout: 1800
                  exit_code: 0
              qos_options: {prefetch_size: 0, prefetch_count: 1, global: false}
          update_feed_8:
              connection: default
              exchange_options: {name: 'old_sound_rabbit_mq.update_feed', type: x-consistent-hash}
              queue_options: {name: 'old_sound_rabbit_mq.consumer.update_feed_8', routing_key: '1'}
              callback: App\Controller\Amqp\UpdateFeed\Consumer
              idle_timeout: 300
              idle_timeout_exit_code: 0
              graceful_max_execution:
                  timeout: 1800
                  exit_code: 0
              qos_options: {prefetch_size: 0, prefetch_count: 1, global: false}
          update_feed_9:
              connection: default
              exchange_options: {name: 'old_sound_rabbit_mq.update_feed', type: x-consistent-hash}
              queue_options: {name: 'old_sound_rabbit_mq.consumer.update_feed_9', routing_key: '1'}
              callback: App\Controller\Amqp\UpdateFeed\Consumer
              idle_timeout: 300
              idle_timeout_exit_code: 0
              graceful_max_execution:
                  timeout: 1800
                  exit_code: 0
              qos_options: {prefetch_size: 0, prefetch_count: 1, global: false}
          ```
12. Добавляем новые консьюмеры в конфигурацию `supervisor` в файле `supervisor/consumer.conf`
     ```ini
     [program:update_feed_0]
     command=php -dmemory_limit=1G /app/bin/console rabbitmq:consumer -m 100 update_feed_0 --env=dev -vv
     process_name=update_feed_0_%(process_num)02d
     numprocs=1
     directory=/tmp
     autostart=true
     autorestart=true
     startsecs=3
     startretries=10
     user=www-data
     redirect_stderr=false
     stdout_logfile=/app/var/log/supervisor.update_feed.out.log
     stdout_capture_maxbytes=1MB
     stderr_logfile=/app/var/log/supervisor.update_feed.error.log
     stderr_capture_maxbytes=1MB
    
     [program:update_feed_1]
     command=php -dmemory_limit=1G /app/bin/console rabbitmq:consumer -m 100 update_feed_1 --env=dev -vv
     process_name=update_feed_1_%(process_num)02d
     numprocs=1
     directory=/tmp
     autostart=true
     autorestart=true
     startsecs=3
     startretries=10
     user=www-data
     redirect_stderr=false
     stdout_logfile=/app/var/log/supervisor.update_feed.out.log
     stdout_capture_maxbytes=1MB
     stderr_logfile=/app/var/log/supervisor.update_feed.error.log
     stderr_capture_maxbytes=1MB
    
     [program:update_feed_2]
     command=php -dmemory_limit=1G /app/bin/console rabbitmq:consumer -m 100 update_feed_2 --env=dev -vv
     process_name=update_feed_2_%(process_num)02d
     numprocs=1
     directory=/tmp
     autostart=true
     autorestart=true
     startsecs=3
     startretries=10
     user=www-data
     redirect_stderr=false
     stdout_logfile=/app/var/log/supervisor.update_feed.out.log
     stdout_capture_maxbytes=1MB
     stderr_logfile=/app/var/log/supervisor.update_feed.error.log
     stderr_capture_maxbytes=1MB
    
     [program:update_feed_3]
     command=php -dmemory_limit=1G /app/bin/console rabbitmq:consumer -m 100 update_feed_3 --env=dev -vv
     process_name=update_feed_3_%(process_num)02d
     numprocs=1
     directory=/tmp
     autostart=true
     autorestart=true
     startsecs=3
     startretries=10
     user=www-data
     redirect_stderr=false
     stdout_logfile=/app/var/log/supervisor.update_feed.out.log
     stdout_capture_maxbytes=1MB
     stderr_logfile=/app/var/log/supervisor.update_feed.error.log
     stderr_capture_maxbytes=1MB
    
     [program:update_feed_4]
     command=php -dmemory_limit=1G /app/bin/console rabbitmq:consumer -m 100 update_feed_4 --env=dev -vv
     process_name=update_feed_4_%(process_num)02d
     numprocs=1
     directory=/tmp
     autostart=true
     autorestart=true
     startsecs=3
     startretries=10
     user=www-data
     redirect_stderr=false
     stdout_logfile=/app/var/log/supervisor.update_feed.out.log
     stdout_capture_maxbytes=1MB
     stderr_logfile=/app/var/log/supervisor.update_feed.error.log
     stderr_capture_maxbytes=1MB
    
     [program:update_feed_5]
     command=php -dmemory_limit=1G /app/bin/console rabbitmq:consumer -m 100 update_feed_5 --env=dev -vv
     process_name=update_feed_5_%(process_num)02d
     numprocs=1
     directory=/tmp
     autostart=true
     autorestart=true
     startsecs=3
     startretries=10
     user=www-data
     redirect_stderr=false
     stdout_logfile=/app/var/log/supervisor.update_feed.out.log
     stdout_capture_maxbytes=1MB
     stderr_logfile=/app/var/log/supervisor.update_feed.error.log
     stderr_capture_maxbytes=1MB
    
     [program:update_feed_6]
     command=php -dmemory_limit=1G /app/bin/console rabbitmq:consumer -m 100 update_feed_6 --env=dev -vv
     process_name=update_feed_6_%(process_num)02d
     numprocs=1
     directory=/tmp
     autostart=true
     autorestart=true
     startsecs=3
     startretries=10
     user=www-data
     redirect_stderr=false
     stdout_logfile=/app/var/log/supervisor.update_feed.out.log
     stdout_capture_maxbytes=1MB
     stderr_logfile=/app/var/log/supervisor.update_feed.error.log
     stderr_capture_maxbytes=1MB
    
     [program:update_feed_7]
     command=php -dmemory_limit=1G /app/bin/console rabbitmq:consumer -m 100 update_feed_7 --env=dev -vv
     process_name=update_feed_7_%(process_num)02d
     numprocs=1
     directory=/tmp
     autostart=true
     autorestart=true
     startsecs=3
     startretries=10
     user=www-data
     redirect_stderr=false
     stdout_logfile=/app/var/log/supervisor.update_feed.out.log
     stdout_capture_maxbytes=1MB
     stderr_logfile=/app/var/log/supervisor.update_feed.error.log
     stderr_capture_maxbytes=1MB
    
     [program:update_feed_8]
     command=php -dmemory_limit=1G /app/bin/console rabbitmq:consumer -m 100 update_feed_8 --env=dev -vv
     process_name=update_feed_8_%(process_num)02d
     numprocs=1
     directory=/tmp
     autostart=true
     autorestart=true
     startsecs=3
     startretries=10
     user=www-data
     redirect_stderr=false
     stdout_logfile=/app/var/log/supervisor.update_feed.out.log
     stdout_capture_maxbytes=1MB
     stderr_logfile=/app/var/log/supervisor.update_feed.error.log
     stderr_capture_maxbytes=1MB
    
     [program:update_feed_9]
     command=php -dmemory_limit=1G /app/bin/console rabbitmq:consumer -m 100 update_feed_9 --env=dev -vv
     process_name=update_feed_9_%(process_num)02d
     numprocs=1
     directory=/tmp
     autostart=true
     autorestart=true
     startsecs=3
     startretries=10
     user=www-data
     redirect_stderr=false
     stdout_logfile=/app/var/log/supervisor.update_feed.out.log
     stdout_capture_maxbytes=1MB
     stderr_logfile=/app/var/log/supervisor.update_feed.error.log
     stderr_capture_maxbytes=1MB
     ```
13. Перезапускаем контейнер `supervisor` командой `docker-compose restart supervisor`
14. Видим, что в RabbitMQ появились очереди с консьюмерами и точка обмена типа `x-consistent-hash`
15. Выполняем запрос Post tweet из Postman-коллекции v8 с параметром `async` = 1
16. В интерфейсе RabbitMQ можно увидеть, что в некоторые очереди насыпались сообщения, но сложно оценить равномерность
    распределения

## Добавляем мониторинг

1. Исправляем класс `App\Controller\Amqp\UpdateFeed\Consumer`
    ```php
    <?php
    
    namespace App\Controller\Amqp\UpdateFeed;
    
    use App\Application\RabbitMq\AbstractConsumer;
    use App\Controller\Amqp\UpdateFeed\Input\Message;
    use App\Domain\Entity\User;
    use App\Domain\Model\TweetModel;
    use App\Domain\Service\FeedService;
    use App\Domain\Service\UserService;
    use App\Infrastructure\Storage\MetricsStorage;
    
    class Consumer extends AbstractConsumer
    {
        public function __construct(
            private readonly FeedService $feedService,
            private readonly UserService $userService,
            private readonly MetricsStorage $metricsStorage,
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
2. Добавляем в `config/services.yaml` инъекцию названий метрик в консьюмеры
    ```yaml
    App\Controller\Amqp\UpdateFeed\Consumer0:
        class: App\Controller\Amqp\UpdateFeed\Consumer
        arguments:
            $key: 'update_feed_0'

    App\Controller\Amqp\UpdateFeed\Consumer1:
        class: App\Controller\Amqp\UpdateFeed\Consumer
        arguments:
            $key: 'update_feed_1'

    App\Controller\Amqp\UpdateFeed\Consumer2:
        class: App\Controller\Amqp\UpdateFeed\Consumer
        arguments:
            $key: 'update_feed_2'

    App\Controller\Amqp\UpdateFeed\Consumer3:
        class: App\Controller\Amqp\UpdateFeed\Consumer
        arguments:
            $key: 'update_feed_3'

    App\Controller\Amqp\UpdateFeed\Consumer4:
        class: App\Controller\Amqp\UpdateFeed\Consumer
        arguments:
            $key: 'update_feed_4'

    App\Controller\Amqp\UpdateFeed\Consumer5:
        class: App\Controller\Amqp\UpdateFeed\Consumer
        arguments:
            $key: 'update_feed_5'

    App\Controller\Amqp\UpdateFeed\Consumer6:
        class: App\Controller\Amqp\UpdateFeed\Consumer
        arguments:
            $key: 'update_feed_6'

    App\Controller\Amqp\UpdateFeed\Consumer7:
        class: App\Controller\Amqp\UpdateFeed\Consumer
        arguments:
            $key: 'update_feed_7'

    App\Controller\Amqp\UpdateFeed\Consumer8:
        class: App\Controller\Amqp\UpdateFeed\Consumer
        arguments:
            $key: 'update_feed_8'

    App\Controller\Amqp\UpdateFeed\Consumer9:
        class: App\Controller\Amqp\UpdateFeed\Consumer
        arguments:
            $key: 'update_feed_9'            
    ```
3. В файл `config/packages/old_sound_rabbit_mq.yaml` в секции `consumers` исправляем коллбэки для каждого консьюмера на
`App\Controller\Amqp\UpdateFeed\ConsumerK`
    ```yaml
    update_feed_0:
      connection: default
      exchange_options: {name: 'old_sound_rabbit_mq.update_feed', type: x-consistent-hash}
      queue_options: {name: 'old_sound_rabbit_mq.consumer.update_feed_0', routing_key: '1'}
      callback: App\Controller\Amqp\UpdateFeed\Consumer0
      idle_timeout: 300
      idle_timeout_exit_code: 0
      graceful_max_execution:
        timeout: 1800
        exit_code: 0
      qos_options: {prefetch_size: 0, prefetch_count: 1, global: false}
    update_feed_1:
      connection: default
      exchange_options: {name: 'old_sound_rabbit_mq.update_feed', type: x-consistent-hash}
      queue_options: {name: 'old_sound_rabbit_mq.consumer.update_feed_1', routing_key: '1'}
      callback: App\Controller\Amqp\UpdateFeed\Consumer1
      idle_timeout: 300
      idle_timeout_exit_code: 0
      graceful_max_execution:
        timeout: 1800
        exit_code: 0
      qos_options: {prefetch_size: 0, prefetch_count: 1, global: false}
    update_feed_2:
      connection: default
      exchange_options: {name: 'old_sound_rabbit_mq.update_feed', type: x-consistent-hash}
      queue_options: {name: 'old_sound_rabbit_mq.consumer.update_feed_2', routing_key: '1'}
      callback: App\Controller\Amqp\UpdateFeed\Consumer2
      idle_timeout: 300
      idle_timeout_exit_code: 0
      graceful_max_execution:
        timeout: 1800
        exit_code: 0
      qos_options: {prefetch_size: 0, prefetch_count: 1, global: false}
    update_feed_3:
      connection: default
      exchange_options: {name: 'old_sound_rabbit_mq.update_feed', type: x-consistent-hash}
      queue_options: {name: 'old_sound_rabbit_mq.consumer.update_feed_3', routing_key: '1'}
      callback: App\Controller\Amqp\UpdateFeed\Consumer3
      idle_timeout: 300
      idle_timeout_exit_code: 0
      graceful_max_execution:
        timeout: 1800
        exit_code: 0
      qos_options: {prefetch_size: 0, prefetch_count: 1, global: false}
    update_feed_4:
      connection: default
      exchange_options: {name: 'old_sound_rabbit_mq.update_feed', type: x-consistent-hash}
      queue_options: {name: 'old_sound_rabbit_mq.consumer.update_feed_4', routing_key: '1'}
      callback: App\Controller\Amqp\UpdateFeed\Consumer4
      idle_timeout: 300
      idle_timeout_exit_code: 0
      graceful_max_execution:
        timeout: 1800
        exit_code: 0
      qos_options: {prefetch_size: 0, prefetch_count: 1, global: false}
    update_feed_5:
      connection: default
      exchange_options: {name: 'old_sound_rabbit_mq.update_feed', type: x-consistent-hash}
      queue_options: {name: 'old_sound_rabbit_mq.consumer.update_feed_5', routing_key: '1'}
      callback: App\Controller\Amqp\UpdateFeed\Consumer5
      idle_timeout: 300
      idle_timeout_exit_code: 0
      graceful_max_execution:
        timeout: 1800
        exit_code: 0
      qos_options: {prefetch_size: 0, prefetch_count: 1, global: false}
    update_feed_6:
      connection: default
      exchange_options: {name: 'old_sound_rabbit_mq.update_feed', type: x-consistent-hash}
      queue_options: {name: 'old_sound_rabbit_mq.consumer.update_feed_6', routing_key: '1'}
      callback: App\Controller\Amqp\UpdateFeed\Consumer6
      idle_timeout: 300
      idle_timeout_exit_code: 0
      graceful_max_execution:
        timeout: 1800
        exit_code: 0
      qos_options: {prefetch_size: 0, prefetch_count: 1, global: false}
    update_feed_7:
      connection: default
      exchange_options: {name: 'old_sound_rabbit_mq.update_feed', type: x-consistent-hash}
      queue_options: {name: 'old_sound_rabbit_mq.consumer.update_feed_7', routing_key: '1'}
      callback: App\Controller\Amqp\UpdateFeed\Consumer7
      idle_timeout: 300
      idle_timeout_exit_code: 0
      graceful_max_execution:
        timeout: 1800
        exit_code: 0
      qos_options: {prefetch_size: 0, prefetch_count: 1, global: false}
    update_feed_8:
      connection: default
      exchange_options: {name: 'old_sound_rabbit_mq.update_feed', type: x-consistent-hash}
      queue_options: {name: 'old_sound_rabbit_mq.consumer.update_feed_8', routing_key: '1'}
      callback: App\Controller\Amqp\UpdateFeed\Consumer8
      idle_timeout: 300
      idle_timeout_exit_code: 0
      graceful_max_execution:
        timeout: 1800
        exit_code: 0
      qos_options: {prefetch_size: 0, prefetch_count: 1, global: false}
    update_feed_9:
      connection: default
      exchange_options: {name: 'old_sound_rabbit_mq.update_feed', type: x-consistent-hash}
      queue_options: {name: 'old_sound_rabbit_mq.consumer.update_feed_9', routing_key: '1'}
      callback: App\Controller\Amqp\UpdateFeed\Consumer9
      idle_timeout: 300
      idle_timeout_exit_code: 0
      graceful_max_execution:
        timeout: 1800
        exit_code: 0
      qos_options: {prefetch_size: 0, prefetch_count: 1, global: false}
    ```
4. Перезапускаем контейнер `supervisor` командой `docker-compose restart supervisor`
5. Выполняем несколько запросов Post tweet из Postman-коллекции v8 с параметром `async` = 1
6. Заходим в Grafana по адресу `localhost:3000` с логином / паролем `admin` / `admin`
7. Добавляем Data source с типом Graphite и url `http://graphite:80`
8. Добавляем Dashboard и Panel
9. В панели отображаем графики для метрик stats_counts.my_app.update_feedX, где X – номер консьюмера
10. Видим, что распределение не очень равномерное

## Балансируем консьюмеры

1. В файл `config/packages/old_sound_rabbit_mq.yaml` в секции `consumers` исправляем для каждого консьюмера значение на
   `routing_key` на 20
2. Перезапускаем контейнер `supervisor` командой `docker-compose restart supervisor`
3. Выполняем несколько запросов Post tweet из Postman-коллекции v9 с параметром `async` = 1
4. Видим, что распределение стало гораздо равномернее   

