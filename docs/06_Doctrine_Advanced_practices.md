# Doctrine. Дополнительные возможности

Запускаем контейнеры командой `docker-compose up -d`

## Добавляем soft-delete через фильтр

1. Добавляем интерфейс `App\Domain\Entity\SoftDeleteableInterface`
    ```php
    <?php
   
    namespace App\Domain\Entity;

    use DateTime;

    interface SoftDeletableInterface
    {
        public function getDeletedAt(): ?DateTime;
   
        public function setDeletedAt(): void;
    }
    ```
2. В классе `App\Domain\Entity\User` имплементируем интерфейс, добавляя новое поле, геттер и сеттер
    ```php
    #[ORM\Column(name: 'deleted_at', type: 'datetime', nullable: true)]
    private ?DateTime $deletedAt = null;

    public function getDeletedAt(): ?DateTime
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(): void
    {
        $this->deletedAt = new DateTime();
    }
    ```
3. Добавляем класс `App\Application\Doctrine\SoftDeletedFilter`
    ```php
    <?php
    
    namespace App\Application\Doctrine;
    
    use App\Domain\Entity\SoftDeletableInterface;
    use Doctrine\ORM\Mapping\ClassMetadata;
    use Doctrine\ORM\Query\Filter\SQLFilter;
    
    class SoftDeletedFilter extends SQLFilter
    {
        public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias): string
        {
            if (!$targetEntity->reflClass->implementsInterface(SoftDeletableInterface::class)) {
                return '';
            }
    
            return $targetTableAlias.'.deleted_at IS NULL';
        }
    }
    ```
4. В файле `config/packages/doctrine.yaml` добавляем в секцию `doctrine.orm` подсекцию `filters`
    ```yaml
    filters:
        soft_delete_filter:
            class: App\Application\Doctrine\SoftDeletedFilter
            enabled: true
    ```
5. В классе `App\Infrastructure\Repository\UserRepository` добавляем метод `remove`
    ```php
    public function remove(User $user): void
    {
        $user->setDeletedAt();
        $this->flush();
    }
    ```
6. В классе `App\Domain\Service\UserService` добавляем метод `removeById`
    ```php
    public function removeById(int $userId): void
    {
        $user = $this->userRepository->find($userId);
        if ($user instanceof User) {
            $this->userRepository->remove($user);
        }
    }
    ```
7. Исправляем класс `App\Controller\WorldController`
    ```php
    <?php
    
    namespace App\Controller;
    
    use App\Domain\Entity\User;
    use App\Domain\Service\UserService;
    use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
    use Symfony\Component\HttpFoundation\Response;
    
    class WorldController extends AbstractController
    {
        public function __construct(
            private readonly UserService $userService,
        ) {
        }
    
        public function hello(): Response
        {
            $user = $this->userService->create('Jack London');
            $this->userService->removeById($user->getId());
            $usersByLogin = $this->userService->findUsersByLogin($user->getLogin());
    
            return $this->json(['users' => array_map(static fn (User $user) => $user->toArray(), $usersByLogin)]);
        }
    }
    ```
8. Входим в контейнер командой `docker exec -it php sh`, дальнейшие команды будут выполняться из контейнера
9. Выполняем команду `php bin/console doctrine:migrations:diff`
10. Проверяем полученную миграцию и применяем её командой `php bin/console doctrine:migrations:migrate`
11. Заходим по адресу `http://localhost:7777/world/hello`, видим в ответе пустой массив, но в БД пользователь
    присутствует

## Добавляем параметризацию для фильтра

1. Исправляем класс `App\Application\Doctrine\SoftDeletedFilter`
    ```php
    <?php
    
    namespace App\Application\Doctrine;
    
    use App\Domain\Entity\SoftDeletableInterface;
    use Doctrine\ORM\Mapping\ClassMetadata;
    use Doctrine\ORM\Query\Filter\SQLFilter;
    
    class SoftDeletedFilter extends SQLFilter
    {
        public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias): string
        {
            if (!$targetEntity->reflClass->implementsInterface(SoftDeletableInterface::class)) {
                return '';
            }
    
        return $this->getParameter('checkTime') ? 
            '('.$targetTableAlias.'.deleted_at IS NULL OR '.$targetTableAlias.'.deleted_at >= current_timestamp)' :
            $targetTableAlias.'.deleted_at IS NULL';
        }
    }
    ```
2. В файле `config/packages/doctrine.yaml` исправляем секцию `doctrine.orm.filters`
    ```yaml
    filters:
        soft_delete_filter:
            class: App\Application\Doctrine\SoftDeletedFilter
            enabled: true
            parameters:
              checkTime: true
    ```
3. Добавляем интерфейс `App\Domain\Entity\SoftDeleteableInFutureInterface`
    ```php
    <?php
   
    namespace App\Domain\Entity;
   
    use DateInterval;
   
    interface SoftDeletableInFutureInterface
    {
        public function setDeletedAtInFuture(DateInterval $dateInterval): void;
    }
    ```
4. В классе `App\Domain\Entity\User` имплементируем интерфейс
    ```php
    public function setDeletedAtInFuture(DateInterval $dateInterval): void
    {
        if ($this->deletedAt === null) {
            $this->deletedAt = new DateTime();
        }
        $this->deletedAt = $this->deletedAt->add($dateInterval);
    }
    ```
5. В классе `App\Infrastructure\Repository\UserRepository` добавляем метод `removeInFuture`
    ```php
    public function removeInFuture(User $user, DateInterval $dateInterval): void
    {
        $user->setDeletedAtInFuture($dateInterval);
        $this->flush();
    }
    ```
6. В классе `App\Domain\Service\UserService` добавляем метод `removeByIdInFuture`
    ```php
    public function removeByIdInFuture(int $userId, DateInterval $dateInterval): void
    {
        $user = $this->userRepository->find($userId);
        if ($user instanceof User) {
            $this->userRepository->removeInFuture($user, $dateInterval);
        }
    }
    ```
7. Исправляем класс `App\Controller\WorldController`
    ```php
    <?php
    
    namespace App\Controller;
    
    use App\Domain\Entity\User;
    use App\Domain\Service\UserService;
    use DateInterval;
    use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
    use Symfony\Component\HttpFoundation\Response;
    
    class WorldController extends AbstractController
    {
        public function __construct(
            private readonly UserService $userService,
        ) {
        }
    
        public function hello(): Response
        {
            $user = $this->userService->create('Terry Pratchett');
            $this->userService->removeByIdInFuture($user->getId(), DateInterval::createFromDateString('+5 min'));
            $usersByLogin = $this->userService->findUsersByLogin($user->getLogin());

            return $this->json(['users' => array_map(static fn (User $user) => $user->toArray(), $usersByLogin)]);
        }
    }
    ```
8. Заходим по адресу `http://localhost:7777/world/hello`, видим в ответе пользователя, хотя поле `deleted_at` заполнено
9. В классе `App\Controller\WorldController` исправляем метод `hello`
    ```php
    public function hello(): Response
    {
        $user = $this->userService->create('William Shakespeare');
        $this->userService->removeById($user->getId());
        $usersByLogin = $this->userService->findUsersByLogin($user->getLogin());

        return $this->json(['users' => array_map(static fn (User $user) => $user->toArray(), $usersByLogin)]);
    }
    ```
10. Заходим по адресу `http://localhost:7777/world/hello`, видим пустой массив

## Отключаем фильтр в коде

1. В классе `App\Infrastructure\Repository\UserRepository` добавляем метод `findUsersByLoginWithDeleted`
    ```php
    /**
     * @return User[]
     */
    public function findUsersByLoginWithDeleted(string $name): array
    {
        $filters = $this->entityManager->getFilters();
        if ($filters->isEnabled('soft_delete_filter')) {
            $filters->disable('soft_delete_filter');
        }
        return $this->entityManager->getRepository(User::class)->findBy(['login' => $name]);
    }
    ```
2. В классе `App\Domain\Service\UserService` добавляем метод `findUsersByLoginWithDeleted`
    ```php
    /**
     * @return User[]
     */
    public function findUsersByLoginWithDeleted(string $login): array
    {
        return $this->userRepository->findUsersByLoginWithDeleted($login);
    }
    ```
3. В классе `App\Controller\WorldController` исправляем метод `hello`
    ```php
    public function hello(): Response
    {
        $user = $this->userService->create('William Shakespeare');
        $this->userService->removeById($user->getId());
        $usersByLogin = $this->userService->findUsersByLoginWithDeleted($user->getLogin());

        return $this->json(['users' => array_map(static fn (User $user) => $user->toArray(), $usersByLogin)]);
    }
    ```
4. Заходим по адресу `http://localhost:7777/world/hello`, видим удалённые записи

## Добавляем кастомный тип для Doctrine

1. Добавляем класс `App\Domain\ValueObject\CommunicationChannel`
    ```php
    <?php
    
    namespace App\Domain\ValueObject;
    
    use RuntimeException;
    
    class CommunicationChannel
    {
        private const EMAIL = 'email';
        private const PHONE = 'phone';
        private const ALLOWED_VALUES = [self::PHONE, self::EMAIL];
    
        private function __construct(private readonly string $value)
        {
        }
    
        public static function fromString(string $value): self
        {
            if (!in_array($value, self::ALLOWED_VALUES, true)) {
                throw new RuntimeException('Invalid communication channel value');
            }
    
            return new self($value);
        }
    
        public function getValue(): string
        {
            return $this->value;
        }
    }
    ```
2. Добавляем класс `App\Application\Doctrine\Types\CommunicationChannelType`
    ```php
    <?php
    
    namespace App\Application\Doctrine\Types;
    
    use App\Domain\ValueObject\CommunicationChannel;
    use Doctrine\DBAL\Platforms\AbstractPlatform;
    use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
    use Doctrine\DBAL\Types\Type;
    use RuntimeException;
    
    class CommunicationChannelType extends Type
    {
        public function convertToPHPValue($value, AbstractPlatform $platform): ?CommunicationChannel
        {
            if ($value === null) {
                return null;
            }
    
            if (is_string($value)) {
                try {
                    return CommunicationChannel::fromString($value);
                } catch (RuntimeException) {
                }
            }
    
            throw ValueNotConvertible::new($value, $this->getName());
        }
    
        public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
        {
            if ($value === null) {
                return null;
            }
    
            if ($value instanceof CommunicationChannel) {
                return $value->getValue();
            }
    
            throw ValueNotConvertible::new($value, $this->getName());
        }
    
        public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
        {
            return $platform->getStringTypeDeclarationSQL($column);
        }
    
        public function getName()
        {
            return 'communicationChannel';
        }
    }
    ```
3. В файле `config/packages/doctrine.yaml` добавляем в секцию `doctrine.dbal` подсекцию `types`
    ```yaml
    types:
        communicationChannel: App\Application\Doctrine\Types\CommunicationChannelType
    ```
4. В классе `App\Domain\Entity\User`
   1. добавляем новое поле, геттер и сеттер
        ```php
        #[ORM\Column(name: 'communication_channel', type: 'communicationChannel', nullable: true)]
        private ?CommunicationChannel $communicationChannel = null;
 
        public function getCommunicationChannel(): ?CommunicationChannel
        {
            return $this->communicationChannel;
        }
 
        public function setCommunicationChannel(?CommunicationChannel $communicationChannel): void
        {
            $this->communicationChannel = $communicationChannel;
        }
        ```
   2. исправляем метод `toArray`
        ```php
        public function toArray(): array
        {
            return [
                'id' => $this->id,
                'login' => $this->login,
                'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
                'updatedAt' => $this->updatedAt->format('Y-m-d H:i:s'),
                'communicationChannel' => $this->communicationChannel->getValue(),
                'tweets' => array_map(static fn(Tweet $tweet) => $tweet->toArray(), $this->tweets->toArray()),
                'followers' => array_map(
                    static fn(User $user) => ['id' => $user->getId(), 'login' => $user->getLogin()],
                    $this->followers->toArray()
                ),
                'authors' => array_map(
                    static fn(User $user) => ['id' => $user->getId(), 'login' => $user->getLogin()],
                    $this->authors->toArray()
                ),
                'subscriptionFollowers' => array_map(
                    static fn(Subscription $subscription) => [
                        'subscriptionId' => $subscription->getId(),
                        'userId' => $subscription->getFollower()->getId(),
                        'login' => $subscription->getFollower()->getLogin(),
                    ],
                    $this->subscriptionFollowers->toArray()
                ),
                'subscriptionAuthors' => array_map(
                    static fn(Subscription $subscription) => [
                        'subscriptionId' => $subscription->getId(),
                        'userId' => $subscription->getAuthor()->getId(),
                        'login' => $subscription->getAuthor()->getLogin(),
                    ],
                    $this->subscriptionAuthors->toArray()
                ),
            ];
        }      
        ```
5. В классе `App\Domain\Service\UserService` исправляем метод `create`
    ```php
    public function create(string $login, string $communicationChannel): User
    {
        $user = new User();
        $user->setLogin($login);
        $user->setCommunicationChannel(CommunicationChannel::fromString($communicationChannel));
        $this->userRepository->create($user);

        return $user;
    }
    ```
6. В классе `App\Controller\WorldController` исправляем метод `hello`
    ```php
    public function hello(): Response
    {
        $user = $this->userService->create('Howard Lovecraft', 'email');

        return $this->json(['user' => $user->toArray()]);
    }
    ```
7. Выполняем команду `php bin/console doctrine:migrations:diff`
8. Проверяем полученную миграцию и применяем её командой `php bin/console doctrine:migrations:migrate`
9. Заходим по адресу `http://localhost:7777/world/hello`, видим установленный канал связи

## Заменяем кастомный тип на перечисление

1. Добавляем перечисление `App\Domain\ValueObject\CommunicationChannelEnum`
    ```php
    <?php
    
    namespace App\Domain\ValueObject;
    
    enum CommunicationChannelEnum: string
    {
        case Email = 'email';
        case Phone = 'phone';
    }
    ```
2. В классе `App\Domain\Entity\User`
   1. Исправляем описание поля `$communicationChannel`, его геттера и сеттера
        ```php
        #[ORM\Column(name: 'communication_channel', type: 'string', nullable: true, enumType: CommunicationChannelEnum::class)]
        private ?CommunicationChannelEnum $communicationChannel = null;
  
        public function getCommunicationChannel(): ?CommunicationChannelEnum
        {
            return $this->communicationChannel;
        }

        public function setCommunicationChannel(?CommunicationChannelEnum $communicationChannel): void
        {
            $this->communicationChannel = $communicationChannel;
        }
        ```
   2. Исправляем метод `toArray`
        ```php
        public function toArray(): array
        {
            return [
                'id' => $this->id,
                'login' => $this->login,
                'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
                'updatedAt' => $this->updatedAt->format('Y-m-d H:i:s'),
                'communicationChannel' => $this->communicationChannel->value,
                'tweets' => array_map(static fn(Tweet $tweet) => $tweet->toArray(), $this->tweets->toArray()),
                'followers' => array_map(
                    static fn(User $user) => ['id' => $user->getId(), 'login' => $user->getLogin()],
                    $this->followers->toArray()
                ),
                'authors' => array_map(
                    static fn(User $user) => ['id' => $user->getId(), 'login' => $user->getLogin()],
                    $this->authors->toArray()
                ),
                'subscriptionFollowers' => array_map(
                    static fn(Subscription $subscription) => [
                        'subscriptionId' => $subscription->getId(),
                        'userId' => $subscription->getFollower()->getId(),
                        'login' => $subscription->getFollower()->getLogin(),
                    ],
                    $this->subscriptionFollowers->toArray()
                ),
                'subscriptionAuthors' => array_map(
                    static fn(Subscription $subscription) => [
                        'subscriptionId' => $subscription->getId(),
                        'userId' => $subscription->getAuthor()->getId(),
                        'login' => $subscription->getAuthor()->getLogin(),
                    ],
                    $this->subscriptionAuthors->toArray()
                ),
            ];
        }
        ```
3. В классе `App\Domain\Service\UserService` исправляем метод `create`
    ```php
    public function create(string $login, string $communicationChannel): User
    {
        $user = new User();
        $user->setLogin($login);
        $user->setCommunicationChannel(CommunicationChannelEnum::from($communicationChannel));
        $this->userRepository->create($user);

        return $user;
    }
    ```
4. Выполняем команду `php bin/console doctrine:migrations:diff`
5. Проверяем полученную миграцию и применяем её командой `php bin/console doctrine:migrations:migrate`
6. Заходим по адресу `http://localhost:7777/world/hello`, видим установленный канал связи

## Используем наследование сущностей с разными таблицами

1. Добавляем класс `App\Domain\Entity\EmailUser`
    ```php
    <?php
    
    namespace App\Domain\Entity;
    
    use Doctrine\ORM\Mapping as ORM;
    
    #[ORM\Table(name: '`email_user`')]
    #[ORM\Entity]
    class EmailUser extends User
    {
        #[ORM\Column(type: 'string', nullable: false)]
        private string $email;
    
        public function getEmail(): string
        {
            return $this->email;
        }
    
        public function setEmail(string $email): void
        {
            $this->email = $email;
        }
        
        public function toArray(): array
        {
            return parent::toArray() + ['email' => $this->email];
        }
    }
    ```
2. Добавляем класс `App\Domain\Entity\PhoneUser`
    ```php
    <?php
    
    namespace App\Domain\Entity;
    
    use Doctrine\ORM\Mapping as ORM;
    
    #[ORM\Table(name: 'phone_user')]
    #[ORM\Entity]
    class PhoneUser extends User
    {
        #[ORM\Column(type: 'string', nullable: false)]
        private string $phone;
    
        public function getPhone(): string
        {
            return $this->phone;
        }
    
        public function setPhone(string $phone): void
        {
            $this->phone = $phone;
        }
    
        public function toArray(): array
        {
            return parent::toArray() + ['phone' => $this->phone];
        }
    }
    ```
3. Исправляем класс `App\Domain\Entity\User`
    ```php
    <?php
    
    namespace App\Domain\Entity;
    
    use App\Domain\ValueObject\CommunicationChannelEnum;
    use DateInterval;
    use DateTime;
    use Doctrine\Common\Collections\ArrayCollection;
    use Doctrine\Common\Collections\Collection;
    use Doctrine\ORM\Mapping as ORM;
    
    #[ORM\Table(name: '`user`')]
    #[ORM\Entity]
    #[ORM\HasLifecycleCallbacks]
    #[ORM\InheritanceType('JOINED')]
    #[ORM\DiscriminatorColumn(name: 'communication_channel', type: 'string', enumType: CommunicationChannelEnum::class)]
    #[ORM\DiscriminatorMap(
        [
            CommunicationChannelEnum::Email->value => EmailUser::class,
            CommunicationChannelEnum::Phone->value => PhoneUser::class,
        ]
    )]
    class User implements EntityInterface, HasMetaTimestampsInterface, SoftDeletableInterface, SoftDeletableInFutureInterface
    {
        #[ORM\Column(name: 'id', type: 'bigint', unique: true)]
        #[ORM\Id]
        #[ORM\GeneratedValue(strategy: 'IDENTITY')]
        private ?int $id = null;
    
        #[ORM\Column(type: 'string', length: 32, nullable: false)]
        private string $login;
    
        #[ORM\Column(name: 'created_at', type: 'datetime', nullable: false)]
        private DateTime $createdAt;
    
        #[ORM\Column(name: 'updated_at', type: 'datetime', nullable: false)]
        private DateTime $updatedAt;
    
        #[ORM\OneToMany(targetEntity: Tweet::class, mappedBy: 'author')]
        private Collection $tweets;
    
        #[ORM\ManyToMany(targetEntity: 'User', mappedBy: 'followers')]
        private Collection $authors;
    
        #[ORM\ManyToMany(targetEntity: 'User', inversedBy: 'authors')]
        #[ORM\JoinTable(name: 'author_follower')]
        #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id')]
        #[ORM\InverseJoinColumn(name: 'follower_id', referencedColumnName: 'id')]
        private Collection $followers;
    
        #[ORM\OneToMany(mappedBy: 'follower', targetEntity: 'Subscription')]
        private Collection $subscriptionAuthors;
    
        #[ORM\OneToMany(mappedBy: 'author', targetEntity: 'Subscription')]
        private Collection $subscriptionFollowers;
    
        #[ORM\Column(name: 'deleted_at', type: 'datetime', nullable: true)]
        private ?DateTime $deletedAt = null;
    
        public function __construct()
        {
            $this->tweets = new ArrayCollection();
            $this->authors = new ArrayCollection();
            $this->followers = new ArrayCollection();
            $this->subscriptionAuthors = new ArrayCollection();
            $this->subscriptionFollowers = new ArrayCollection();
        }
    
        public function getId(): int
        {
            return $this->id;
        }
    
        public function setId(int $id): void
        {
            $this->id = $id;
        }
    
        public function getLogin(): string
        {
            return $this->login;
        }
    
        public function setLogin(string $login): void
        {
            $this->login = $login;
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
    
        public function getDeletedAt(): ?DateTime
        {
            return $this->deletedAt;
        }
    
        public function setDeletedAt(): void
        {
            $this->deletedAt = new DateTime();
        }
    
        public function setDeletedAtInFuture(DateInterval $dateInterval): void
        {
            if ($this->deletedAt === null) {
                $this->deletedAt = new DateTime();
            }
            $this->deletedAt = $this->deletedAt->add($dateInterval);
        }
    
        public function addTweet(Tweet $tweet): void
        {
            if (!$this->tweets->contains($tweet)) {
                $this->tweets->add($tweet);
            }
        }
    
        public function addFollower(User $follower): void
        {
            if (!$this->followers->contains($follower)) {
                $this->followers->add($follower);
            }
        }
    
        public function addAuthor(User $author): void
        {
            if (!$this->authors->contains($author)) {
                $this->authors->add($author);
            }
        }
    
        public function addSubscriptionAuthor(Subscription $subscription): void
        {
            if (!$this->subscriptionAuthors->contains($subscription)) {
                $this->subscriptionAuthors->add($subscription);
            }
        }
    
        public function addSubscriptionFollower(Subscription $subscription): void
        {
            if (!$this->subscriptionFollowers->contains($subscription)) {
                $this->subscriptionFollowers->add($subscription);
            }
        }
    
        public function toArray(): array
        {
            return [
                'id' => $this->id,
                'login' => $this->login,
                'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
                'updatedAt' => $this->updatedAt->format('Y-m-d H:i:s'),
                'tweets' => array_map(static fn(Tweet $tweet) => $tweet->toArray(), $this->tweets->toArray()),
                'followers' => array_map(
                    static fn(User $user) => ['id' => $user->getId(), 'login' => $user->getLogin()],
                    $this->followers->toArray()
                ),
                'authors' => array_map(
                    static fn(User $user) => ['id' => $user->getId(), 'login' => $user->getLogin()],
                    $this->authors->toArray()
                ),
                'subscriptionFollowers' => array_map(
                    static fn(Subscription $subscription) => [
                        'subscriptionId' => $subscription->getId(),
                        'userId' => $subscription->getFollower()->getId(),
                        'login' => $subscription->getFollower()->getLogin(),
                    ],
                    $this->subscriptionFollowers->toArray()
                ),
                'subscriptionAuthors' => array_map(
                    static fn(Subscription $subscription) => [
                        'subscriptionId' => $subscription->getId(),
                        'userId' => $subscription->getAuthor()->getId(),
                        'login' => $subscription->getAuthor()->getLogin(),
                    ],
                    $this->subscriptionAuthors->toArray()
                ),
            ];
        }
    }
    ```
4. В классе `App\Domain\Service\UserService` удаляем метод `create` и добавляем методы `createWithPhone` и
   `createWithEmail`
    ```php
    public function createWithPhone(string $login, string $phone): User
    {
        $user = new PhoneUser();
        $user->setLogin($login);
        $user->setPhone($phone);
        $this->userRepository->create($user);

        return $user;
    }

    public function createWithEmail(string $login, string $email): User
    {
        $user = new EmailUser();
        $user->setLogin($login);
        $user->setEmail($email);
        $this->userRepository->create($user);

        return $user;
    }
    ```
5. В классе `App\Controller\WorldController` исправляем метод `hello`
    ```php
    public function hello(): Response
    {
        $this->userService->createWithPhone('Phone user', '+1234567890');
        $this->userService->createWithEmail('Email user', 'my@mail.ru');
        $users = $this->userService->findUsersByLoginWithQueryBuilder('user');

        return $this->json(
            ['users' => array_map(static fn (User $user) => $user->toArray(), $users)]
        );
    }
    ```
6. Выполняем команду `php bin/console doctrine:migrations:diff`
7. Очищаем таблицу `user` в БД, т.к. иначе миграция не сможет примениться
8. Проверяем полученную миграцию и применяем её командой `php bin/console doctrine:migrations:migrate`
9. Заходим по адресу `http://localhost:7777/world/hello`, видим двух пользователей с разными каналами связи

## Используем наследование сущностей в общей таблице

1. Исправляем в классе `App\Domain\Entity\User` значение атрибута `ORM\InheritanceType` на `SINGLE_TABLE`
2. Выполняем команду `php bin/console doctrine:migrations:diff`
3. Очищаем таблицу `user` в БД, т.к. иначе миграция не сможет примениться
4. Проверяем полученную миграцию и применяем её командой `php bin/console doctrine:migrations:migrate`
5. Заходим по адресу `http://localhost:7777/world/hello`, видим двух пользователей с разными каналами связи
