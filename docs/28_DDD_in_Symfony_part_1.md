# DDD в Symfony, часть 1

Запускаем контейнеры командой `docker-compose up -d`

### Делаем XML-маппинг

1. В файле `config/packages/doctrine.yaml` исправляем секцию `doctrine.orm.mappings.App`
    ```yaml
    App:
        type: xml
        is_bundle: false
        dir: '%kernel.project_dir%/src/Infrastructure/Entity'
        prefix: 'App\Domain\Entity'
        alias: App
    ```
2. Добавляем файл `src/Infrastructure/Entity/EmailNotification.orm.xml`
    ```xml
    <doctrine-mapping
        xmlns="http://doctrine-project.org/schemas/orm/doctrine-mapping"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://doctrine-project.org/schemas/orm/doctrine-mapping
                            https://www.doctrine-project.org/schemas/orm/doctrine-mapping.xsd"
    >
        <entity name="App\Domain\Entity\EmailNotification" table="email_notification">
            <id name="id" type="bigint">
                <generator strategy="IDENTITY" />
            </id>
            <field name="email" type="string" length="128" nullable="false" />
            <field name="text" type="string" length="512" nullable="false" />
            <field name="createdAt" type="datetime" nullable="false" />
            <field name="updatedAt" type="datetime" nullable="false" />
    
            <lifecycle-callbacks>
                <lifecycle-callback type="prePersist" method="setCreatedAt"/>
                <lifecycle-callback type="prePersist" method="setUpdatedAt"/>
                <lifecycle-callback type="preUpdate" method="setUpdatedAt"/>
            </lifecycle-callbacks>    
        </entity>
    </doctrine-mapping>
    ```
3. Исправляем класс `App\Domain\Entity\EmailNotification`
    ```php
    <?php
    
    namespace App\Domain\Entity;
    
    use DateTime;
    
    class EmailNotification
    {
        private int $id;
    
        private string $email;
    
        private string $text;
    
        private DateTime $createdAt;
    
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
    
        public function setCreatedAt(): void {
            $this->createdAt = new DateTime();
        }
    
        public function getUpdatedAt(): DateTime {
            return $this->updatedAt;
        }
    
        public function setUpdatedAt(): void {
            $this->updatedAt = new DateTime();
        }
    }
    ```
4. Добавляем файл `src/Infrastructure/Entity/SmsNotification.orm.xml`
    ```xml
    <doctrine-mapping
        xmlns="http://doctrine-project.org/schemas/orm/doctrine-mapping"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://doctrine-project.org/schemas/orm/doctrine-mapping
                            https://www.doctrine-project.org/schemas/orm/doctrine-mapping.xsd"
    >
        <entity name="App\Domain\Entity\SmsNotification" table="sms_notification">
            <id name="id" type="bigint">
                <generator strategy="IDENTITY" />
            </id>
            <field name="phone" type="string" length="11" nullable="false" />
            <field name="text" type="string" length="60" nullable="false" />
            <field name="createdAt" type="datetime" nullable="false" />
            <field name="updatedAt" type="datetime" nullable="false" />
    
            <lifecycle-callbacks>
                <lifecycle-callback type="prePersist" method="setCreatedAt"/>
                <lifecycle-callback type="prePersist" method="setUpdatedAt"/>
                <lifecycle-callback type="preUpdate" method="setUpdatedAt"/>
            </lifecycle-callbacks>    
        </entity>
    </doctrine-mapping>
    ``` 
5. Исправляем класс `App\Domain\Entity\SmsNotification`
    ```php
    <?php
    
    namespace App\Domain\Entity;
    
    use DateTime;
    
    class SmsNotification
    {
        private int $id;
    
        private string $phone;
    
        private string $text;
    
        private DateTime $createdAt;
    
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
    
        public function setCreatedAt(): void {
            $this->createdAt = new DateTime();
        }
    
        public function getUpdatedAt(): DateTime {
            return $this->updatedAt;
        }
    
        public function setUpdatedAt(): void {
            $this->updatedAt = new DateTime();
        }
    }
    ```
6. Добавляем файл `src/Infrastructure/Entity/Subscription.orm.xml`
    ```xml
    <doctrine-mapping
        xmlns="http://doctrine-project.org/schemas/orm/doctrine-mapping"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://doctrine-project.org/schemas/orm/doctrine-mapping
                            https://www.doctrine-project.org/schemas/orm/doctrine-mapping.xsd"
    >
        <entity name="App\Domain\Entity\Subscription" table="subscription">
            <id name="id" type="bigint">
                <generator strategy="IDENTITY" />
            </id>
            <many-to-one field="author" inversed-by="subscriptionFollowers" target-entity="App\Domain\Entity\User">
                <join-column name="author_id" referenced-column-name="id" />
            </many-to-one>
            <many-to-one field="follower" inversed-by="subscriptionAuthors" target-entity="App\Domain\Entity\User">
                <join-column name="follower_id" referenced-column-name="id" />
            </many-to-one>
            <field name="createdAt" type="datetime" nullable="false" />
            <field name="updatedAt" type="datetime" nullable="false" />
    
            <indexes>
                <index name="subscription__author_id__ind" columns="author_id"/>
                <index name="subscription__follower_id__ind" columns="follower_id"/>
            </indexes>
    
            <lifecycle-callbacks>
                <lifecycle-callback type="prePersist" method="setCreatedAt"/>
                <lifecycle-callback type="prePersist" method="setUpdatedAt"/>
                <lifecycle-callback type="preUpdate" method="setUpdatedAt"/>
            </lifecycle-callbacks>    
        </entity>
    </doctrine-mapping>
    ```
7. Исправляем класс `App\Domain\Entity\Subscription`
    ```php
    <?php
    
    namespace App\Domain\Entity;
    
    use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
    use ApiPlatform\Metadata\ApiFilter;
    use ApiPlatform\Metadata\ApiResource;
    use DateTime;
    
    #[ApiResource]
    #[ApiFilter(SearchFilter::class, properties: ['follower.login' => 'partial'])]
    class Subscription implements EntityInterface
    {
        private int $id;
    
        private User $author;
    
        private User $follower;
    
        private DateTime $createdAt;
    
        private DateTime $updatedAt;
    
        public function getId(): int
        {
            return $this->id;
        }
    
        public function setId(int $id): void
        {
            $this->id = $id;
        }
    
        public function getAuthor(): User
        {
            return $this->author;
        }
    
        public function setAuthor(User $author): void
        {
            $this->author = $author;
        }
    
        public function getFollower(): User
        {
            return $this->follower;
        }
    
        public function setFollower(User $follower): void
        {
            $this->follower = $follower;
        }
    
        public function getCreatedAt(): DateTime {
            return $this->createdAt;
        }
    
        public function setCreatedAt(): void {
            $this->createdAt = new DateTime();
        }
    
        public function getUpdatedAt(): DateTime {
            return $this->updatedAt;
        }
    
        public function setUpdatedAt(): void {
            $this->updatedAt = new DateTime();
        }
    }
    ```
8. Добавляем файл `src/Infrastructure/Entity/Tweet.orm.xml`
    ```xml
    <doctrine-mapping
        xmlns="http://doctrine-project.org/schemas/orm/doctrine-mapping"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://doctrine-project.org/schemas/orm/doctrine-mapping
                            https://www.doctrine-project.org/schemas/orm/doctrine-mapping.xsd"
    >
        <entity name="App\Domain\Entity\Tweet" table="tweet">
            <id name="id" type="bigint">
                <generator strategy="IDENTITY" />
            </id>
            <many-to-one field="author" inversed-by="tweets" target-entity="App\Domain\Entity\User">
                <join-column name="author_id" referenced-column-name="id" />
            </many-to-one>
            <field name="text" type="string" length="140" nullable="false" />
            <field name="createdAt" type="datetime" nullable="false" />
            <field name="updatedAt" type="datetime" nullable="false" />
    
            <indexes>
                <index name="tweet__author_id__ind" columns="author_id"/>
            </indexes>
    
            <lifecycle-callbacks>
                <lifecycle-callback type="prePersist" method="setCreatedAt"/>
                <lifecycle-callback type="prePersist" method="setUpdatedAt"/>
                <lifecycle-callback type="preUpdate" method="setUpdatedAt"/>
            </lifecycle-callbacks>    
        </entity>
    </doctrine-mapping>
    ```
9. Исправляем класс `App\Domain\Entity\Tweet`
    ```php
    <?php
    
    namespace App\Domain\Entity;
    
    use DateTime;
    use Symfony\Component\Serializer\Attribute\Groups;
    
    class Tweet implements EntityInterface
    {
        #[Groups(['elastica'])]
        private ?int $id = null;
    
        #[Groups(['elastica'])]
        private User $author;
    
        #[Groups(['elastica'])]
        private string $text;
    
        private DateTime $createdAt;
    
        private DateTime $updatedAt;
    
        public function getId(): int
        {
            return $this->id;
        }
    
        public function setId(int $id): void
        {
            $this->id = $id;
        }
    
        public function getAuthor(): User
        {
            return $this->author;
        }
    
        public function setAuthor(User $author): void
        {
            $this->author = $author;
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
    
        public function setCreatedAt(): void {
            $this->createdAt = new DateTime();
        }
    
        public function getUpdatedAt(): DateTime {
            return $this->updatedAt;
        }
    
        public function setUpdatedAt(): void {
            $this->updatedAt = new DateTime();
        }
    
        public function toArray(): array
        {
            return [
                'id' => $this->id,
                'login' => $this->author->getLogin(),
                'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
                'updatedAt' => $this->updatedAt->format('Y-m-d H:i:s'),
            ];
        }
    }
    ```
10. Добавляем файл `src/Infrastructure/Entity/User.orm.xml`
    ```xml
    <doctrine-mapping
        xmlns="http://doctrine-project.org/schemas/orm/doctrine-mapping"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://doctrine-project.org/schemas/orm/doctrine-mapping
                            https://www.doctrine-project.org/schemas/orm/doctrine-mapping.xsd"
    >
        <entity
            name="App\Domain\Entity\User"
            table="`user`"
            repository-class="App\Application\Doctrine\UserRepository"
            inheritance-type="SINGLE_TABLE"
        >
            <discriminator-column
                name="communication_channel"
                type="string"
                enum-type="App\Domain\ValueObject\CommunicationChannelEnum"
            />
            <discriminator-map>
                <discriminator-mapping value="email" class="App\Domain\Entity\EmailUser"/>
                <discriminator-mapping value="phone" class="App\Domain\Entity\PhoneUser"/>
            </discriminator-map>
            <id name="id" type="bigint">
                <generator strategy="IDENTITY" />
            </id>
            <field name="login" type="string" length="32" nullable="false"/>
            <field name="password" type="string" nullable="false" />
            <field name="age" type="integer" nullable="false" />
            <field name="isActive" type="boolean" nullable="false" />
            <field name="createdAt" type="datetime" nullable="false" />
            <field name="updatedAt" type="datetime" nullable="false" />
            <field name="deletedAt" type="datetime" nullable="true" />
            <field name="avatarLink" type="string" nullable="true" />
            <one-to-many field="tweets" mapped-by="author" target-entity="App\Domain\Entity\Tweet" />
            <many-to-many field="authors" mapped-by="followers" target-entity="App\Domain\Entity\User" />
            <many-to-many field="followers" inversed-by="authors" target-entity="App\Domain\Entity\User">
                <join-table name="author_follower">
                    <join-columns>
                        <join-column name="author_id" referenced-column-name="id"/>
                    </join-columns>
                    <inverse-join-columns>
                        <join-column name="follower_id" referenced-column-name="id"/>
                    </inverse-join-columns>
                </join-table>
            </many-to-many>
            <one-to-many field="subscriptionAuthors" mapped-by="follower" target-entity="App\Domain\Entity\Subscription" />
            <one-to-many field="subscriptionFollowers" mapped-by="author" target-entity="App\Domain\Entity\Subscription" />
            <field name="roles" type="json" length="1024" nullable="false" />
            <field name="token" type="string" length="32" unique="true" nullable="true" />
            <field name="isProtected" type="boolean" nullable="true" />
    
            <unique-constraints>
                <unique-constraint name="user__login__uniq" columns="login">
                    <options>
                        <option name="where">(deleted_at IS NULL)</option>
                    </options>
                </unique-constraint>
            </unique-constraints>
    
            <lifecycle-callbacks>
                <lifecycle-callback type="prePersist" method="setCreatedAt"/>
                <lifecycle-callback type="prePersist" method="setUpdatedAt"/>
                <lifecycle-callback type="preUpdate" method="setUpdatedAt"/>
            </lifecycle-callbacks>
        </entity>
    </doctrine-mapping>
    ```
11. Исправляем класс `App\Domain\Entity\User`
    ```php
    <?php
    
    namespace App\Domain\Entity;
    
    use ApiPlatform\Metadata\ApiResource;
    use ApiPlatform\Metadata\GraphQl\Query;
    use ApiPlatform\Metadata\GraphQl\QueryCollection;
    use ApiPlatform\Metadata\Post;
    use App\Controller\Web\CreateUser\v2\Input\CreateUserDTO;
    use App\Controller\Web\CreateUser\v2\Output\CreatedUserDTO;
    use App\Domain\ApiPlatform\GraphQL\Resolver\UserCollectionResolver;
    use App\Domain\ApiPlatform\GraphQL\Resolver\UserResolver;
    use App\Domain\ApiPlatform\State\UserProcessor;
    use App\Domain\ValueObject\RoleEnum;
    use DateInterval;
    use DateTime;
    use Doctrine\Common\Collections\ArrayCollection;
    use Doctrine\Common\Collections\Collection;
    use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
    use Symfony\Component\Security\Core\User\UserInterface;
    use Symfony\Component\Serializer\Attribute\Groups;
    
    #[ApiResource(
        graphQlOperations: [
            new Query(),
            new QueryCollection(),
            new QueryCollection(resolver: UserCollectionResolver::class, name: 'protected'),
            new Query(
                resolver: UserResolver::class,
                args: ['_id' => ['type' => 'Int'], 'login' => ['type' => 'String']],
                name: 'protected'
            ),
        ]
    )]
    #[Post(input: CreateUserDTO::class, output: CreatedUserDTO::class, processor: UserProcessor::class)]
    class User implements
        EntityInterface,
        HasMetaTimestampsInterface,
        SoftDeletableInterface,
        SoftDeletableInFutureInterface,
        UserInterface,
        PasswordAuthenticatedUserInterface
    {
        #[Groups(['elastica'])]
        private ?int $id = null;
    
        #[Groups(['elastica'])]
        private string $login;
    
        private DateTime $createdAt;
    
        private DateTime $updatedAt;
    
        private Collection $tweets;
    
        private Collection $authors;
    
        private Collection $followers;
    
        private Collection $subscriptionAuthors;
    
        private Collection $subscriptionFollowers;
    
        private ?DateTime $deletedAt = null;
    
        private ?string $avatarLink = null;
    
        private string $password;
    
        #[Groups(['elastica'])]
        private int $age;
    
        private bool $isActive;
    
        private array $roles = [];
    
        private ?string $token = null;
    
        private ?bool $isProtected;
    
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
    
        public function setCreatedAt(): void {
            $this->createdAt = DateTime::createFromFormat('U', (string)time());
        }
    
        public function getUpdatedAt(): DateTime {
            return $this->updatedAt;
        }
    
        public function setUpdatedAt(): void {
            $this->updatedAt = DateTime::createFromFormat('U', (string)time());
        }
    
        public function getDeletedAt(): ?DateTime
        {
            return $this->deletedAt;
        }
    
        public function setDeletedAt(): void
        {
            $this->deletedAt = new DateTime();
        }
    
        public function getAvatarLink(): ?string
        {
            return $this->avatarLink;
        }
    
        public function setAvatarLink(?string $avatarLink): void
        {
            $this->avatarLink = $avatarLink;
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
    
        public function getPassword(): string
        {
            return $this->password;
        }
    
        public function setPassword(string $password): void
        {
            $this->password = $password;
        }
    
        public function getAge(): int
        {
            return $this->age;
        }
    
        public function setAge(int $age): void
        {
            $this->age = $age;
        }
    
        public function isActive(): bool
        {
            return $this->isActive;
        }
    
        public function setIsActive(bool $isActive): void
        {
            $this->isActive = $isActive;
        }
    
        /**
         * @return string[]
         */
        public function getRoles(): array
        {
            $roles = $this->roles;
            // guarantee every user at least has ROLE_USER
            $roles[] = RoleEnum::ROLE_USER->value;
    
            return array_unique($roles);
        }
    
        /**
         * @param string[] $roles
         */
        public function setRoles(array $roles): void
        {
            $this->roles = $roles;
        }
    
        public function getToken(): ?string
        {
            return $this->token;
        }
    
        public function setToken(?string $token): void
        {
            $this->token = $token;
        }
    
        public function eraseCredentials(): void
        {
        }
    
        public function getUserIdentifier(): string
        {
            return $this->login;
        }
    
        /**
         * @return Subscription[]
         */
        public function getSubscriptionFollowers(): array
        {
            return $this->subscriptionFollowers->toArray();
        }
    
        /**
         * @return Subscription[]
         */
        public function getSubscriptionAuthors(): array
        {
            return $this->subscriptionAuthors->toArray();
        }
    
        public function isProtected(): bool
        {
            return $this->isProtected ?? false;
        }
    
        public function setIsProtected(bool $isProtected): void
        {
            $this->isProtected = $isProtected;
        }
    
        public function toArray(): array
        {
            return [
                'id' => $this->id,
                'login' => $this->login,
                'avatar' => $this->avatarLink,
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
12. Добавляем файл `src/Infrastructure/Entity/PhoneUser.orm.xml`
    ```xml
    <doctrine-mapping
        xmlns="http://doctrine-project.org/schemas/orm/doctrine-mapping"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://doctrine-project.org/schemas/orm/doctrine-mapping
                            https://www.doctrine-project.org/schemas/orm/doctrine-mapping.xsd"
    >
        <entity name="App\Domain\Entity\PhoneUser">
            <field name="phone" type="string" length="20" nullable="false"/>
        </entity>
    </doctrine-mapping>
    ```
13. Исправляем класс `App\Domain\Entity\PhoneUser`
    ```php
    <?php
    
    namespace App\Domain\Entity;
    
    use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
    use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
    use ApiPlatform\Metadata\ApiFilter;
    use ApiPlatform\Metadata\ApiResource;
    use Symfony\Component\Serializer\Attribute\Groups;
    
    #[ApiResource]
    #[ApiFilter(SearchFilter::class, properties: ['login' => 'partial'])]
    #[ApiFilter(OrderFilter::class, properties: ['login'])]
    class PhoneUser extends User
    {
        #[Groups(['elastica'])]
        private string $phone;
    
        public function getPhone(): string
        {
            return $this->phone;
        }
    
        public function setPhone(string $phone): self
        {
            $this->phone = $phone;
    
            return $this;
        }
    
        public function toArray(): array
        {
            return parent::toArray() + ['phone' => $this->phone];
        }
    }
    ```
14. Добавляем файл `src/Infrastructure/Entity/EmailUser.orm.xml`
    ```xml
    <doctrine-mapping
        xmlns="http://doctrine-project.org/schemas/orm/doctrine-mapping"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://doctrine-project.org/schemas/orm/doctrine-mapping
                            https://www.doctrine-project.org/schemas/orm/doctrine-mapping.xsd"
    >
        <entity name="App\Domain\Entity\EmailUser">
            <field name="email" type="string" nullable="false"/>
        </entity>
    </doctrine-mapping>
    ```
15. Исправляем класс `App\Domain\Entity\EmailUser`
    ```php
    <?php
    
    namespace App\Domain\Entity;
    
    use Symfony\Component\Serializer\Attribute\Groups;
    
    class EmailUser extends User
    {
        #[Groups(['elastica'])]
        private string $email;
    
        public function getEmail(): string
        {
            return $this->email;
        }
    
        public function setEmail(string $email): self
        {
            $this->email = $email;
    
            return $this;
        }
    
        public function toArray(): array
        {
            return parent::toArray() + ['email' => $this->email];
        }
    }
    ```
16. Выполняем команду `php bin/console doctrine:schema:update --dump-sql`, видим, что изменений нет
17. Выполняем запрос Add user v2 из Postman-коллекции v10. Видим успешный ответ, проверяем, что запись в БД создалась.

### Добавляем ValueObject

1. Добавляем класс `App\Domain\ValueObject\ValueObjectInterface`
    ```php
    <?php
    
    namespace App\Domain\ValueObject;
    
    interface ValueObjectInterface
    {
        public function equals(ValueObjectInterface $other): bool;
    
        public function getValue(): mixed;
    }
    ```
2. Добавляем класс `App\Domain\ValueObject\AbstractValueObjectString`
    ```php
    <?php
    
    namespace App\Domain\ValueObject;
    
    use JsonSerializable;
    
    abstract class AbstractValueObjectString implements ValueObjectInterface, JsonSerializable
    {
        private readonly string $value;
    
        final public function __construct(string $value)
        {
            $this->validate($value);
    
            $this->value = $this->transform($value);
        }
    
        public function __toString(): string
        {
            return $this->value;
        }
    
        public static function fromString(string $value): static
        {
            return new static($value);
        }
    
        public function equals(ValueObjectInterface $other): bool
        {
            return get_class($this) === get_class($other) && $this->getValue() === $other->getValue();
        }
    
        public function getValue(): string
        {
            return $this->value;
        }
    
        public function jsonSerialize(): string
        {
            return $this->value;
        }
    
        protected function validate(string $value): void
        {
        }
    
        protected function transform(string $value): string
        {
            return $value;
        }
    }
    ```
3. Добавляем класс `App\Domain\ValueObject\UserLogin`
    ```php
    <?php
    
    namespace App\Domain\ValueObject;
    
    class UserLogin extends AbstractValueObjectString
    {
    }
    ```
4. Добавляем класс `App\Application\Doctrine\Types\AbstractStringType`
    ```php
    <?php
    
    namespace App\Application\Doctrine\Types;
    
    use App\Domain\ValueObject\AbstractValueObjectString;
    use Doctrine\DBAL\Platforms\AbstractPlatform;
    use Doctrine\DBAL\Types\ConversionException;
    use Doctrine\DBAL\Types\Type;
    
    abstract class AbstractStringType extends Type
    {
        abstract protected function getConcreteValueObjectType(): string;
    
        public function convertToPHPValue($value, AbstractPlatform $platform): ?AbstractValueObjectString
        {
            if ($value === null) {
                return null;
            }
    
            if (is_string($value)) {
                /** @var AbstractValueObjectString $concreteValueObjectType */
                $concreteValueObjectType = $this->getConcreteValueObjectType();
    
                return $concreteValueObjectType::fromString($value);
            }
    
            throw new ConversionException("Could not convert database value $value to {$this->getConcreteValueObjectType()}");
        }
    
        public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
        {
            if ($value === null) {
                return null;
            }
    
            if ($value instanceof AbstractValueObjectString) {
                return $value->getValue();
            }
    
            throw new ConversionException("Could not convert PHP value $value to ".static::class);
        }
    
        public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
        {
            return $platform->getStringTypeDeclarationSQL($column);
        }
    }
    ```
5. Добавляем класс `App\Application\Doctrine\Types\UserLoginType`
    ```php
    <?php
    
    namespace App\Doctrine;
    
    namespace App\Application\Doctrine\Types;

    use App\Domain\ValueObject\UserLogin;

    class UserLoginType extends AbstractStringType
    {
        protected function getConcreteValueObjectType(): string
        {
            return UserLogin::class;
        }
    }
    ```
6. В файле `config/packages/doctrine.yaml` в секцию `doctrine.dbal` исправляем подсекцию `types`
    ```yaml
    types:
        communicationChannel: App\Application\Doctrine\Types\CommunicationChannelType
        userLogin: App\Application\Doctrine\Types\UserLoginType
    ```
7. Исправляем файл `src/Service/Orm/Mapping/User.orm.xml`
    ```xml
    <doctrine-mapping
        xmlns="http://doctrine-project.org/schemas/orm/doctrine-mapping"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://doctrine-project.org/schemas/orm/doctrine-mapping
                            https://www.doctrine-project.org/schemas/orm/doctrine-mapping.xsd"
    >
        <entity
            name="App\Domain\Entity\User"
            table="`user`"
            repository-class="App\Application\Doctrine\UserRepository"
            inheritance-type="SINGLE_TABLE"
        >
            <discriminator-column
                name="communication_channel"
                type="string"
                enum-type="App\Domain\ValueObject\CommunicationChannelEnum"
            />
            <discriminator-map>
                <discriminator-mapping value="email" class="App\Domain\Entity\EmailUser"/>
                <discriminator-mapping value="phone" class="App\Domain\Entity\PhoneUser"/>
            </discriminator-map>
            <id name="id" type="bigint">
                <generator strategy="IDENTITY" />
            </id>
            <field name="login" type="userLogin" length="32" nullable="false"/>
            <field name="password" type="string" nullable="false" />
            <field name="age" type="integer" nullable="false" />
            <field name="isActive" type="boolean" nullable="false" />
            <field name="createdAt" type="datetime" nullable="false" />
            <field name="updatedAt" type="datetime" nullable="false" />
            <field name="deletedAt" type="datetime" nullable="true" />
            <field name="avatarLink" type="string" nullable="true" />
            <one-to-many field="tweets" mapped-by="author" target-entity="App\Domain\Entity\Tweet" />
            <many-to-many field="authors" mapped-by="followers" target-entity="App\Domain\Entity\User" />
            <many-to-many field="followers" inversed-by="authors" target-entity="App\Domain\Entity\User">
                <join-table name="author_follower">
                    <join-columns>
                        <join-column name="author_id" referenced-column-name="id"/>
                    </join-columns>
                    <inverse-join-columns>
                        <join-column name="follower_id" referenced-column-name="id"/>
                    </inverse-join-columns>
                </join-table>
            </many-to-many>
            <one-to-many field="subscriptionAuthors" mapped-by="follower" target-entity="App\Domain\Entity\Subscription" />
            <one-to-many field="subscriptionFollowers" mapped-by="author" target-entity="App\Domain\Entity\Subscription" />
            <field name="roles" type="json" length="1024" nullable="false" />
            <field name="token" type="string" length="32" unique="true" nullable="true" />
            <field name="isProtected" type="boolean" nullable="true" />
    
            <unique-constraints>
                <unique-constraint name="user__login__uniq" columns="login">
                    <options>
                        <option name="where">(deleted_at IS NULL)</option>
                    </options>
                </unique-constraint>
            </unique-constraints>
    
            <lifecycle-callbacks>
                <lifecycle-callback type="prePersist" method="setCreatedAt"/>
                <lifecycle-callback type="prePersist" method="setUpdatedAt"/>
                <lifecycle-callback type="preUpdate" method="setUpdatedAt"/>
            </lifecycle-callbacks>
        </entity>
    </doctrine-mapping>
    ```
8. Исправляем класс `App\Domain\Entity\User`
    ```php
    <?php
    
    namespace App\Domain\Entity;
    
    use ApiPlatform\Metadata\ApiResource;
    use ApiPlatform\Metadata\GraphQl\Query;
    use ApiPlatform\Metadata\GraphQl\QueryCollection;
    use ApiPlatform\Metadata\Post;
    use App\Controller\Web\CreateUser\v2\Input\CreateUserDTO;
    use App\Controller\Web\CreateUser\v2\Output\CreatedUserDTO;
    use App\Domain\ApiPlatform\GraphQL\Resolver\UserCollectionResolver;
    use App\Domain\ApiPlatform\GraphQL\Resolver\UserResolver;
    use App\Domain\ApiPlatform\State\UserProcessor;
    use App\Domain\ValueObject\RoleEnum;
    use App\Domain\ValueObject\UserLogin;
    use DateInterval;
    use DateTime;
    use Doctrine\Common\Collections\ArrayCollection;
    use Doctrine\Common\Collections\Collection;
    use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
    use Symfony\Component\Security\Core\User\UserInterface;
    use Symfony\Component\Serializer\Attribute\Groups;
    
    #[ApiResource(
        graphQlOperations: [
            new Query(),
            new QueryCollection(),
            new QueryCollection(resolver: UserCollectionResolver::class, name: 'protected'),
            new Query(
                resolver: UserResolver::class,
                args: ['_id' => ['type' => 'Int'], 'login' => ['type' => 'String']],
                name: 'protected'
            ),
        ]
    )]
    #[Post(input: CreateUserDTO::class, output: CreatedUserDTO::class, processor: UserProcessor::class)]
    class User implements
        EntityInterface,
        HasMetaTimestampsInterface,
        SoftDeletableInterface,
        SoftDeletableInFutureInterface,
        UserInterface,
        PasswordAuthenticatedUserInterface
    {
        #[Groups(['elastica'])]
        private ?int $id = null;
    
        #[Groups(['elastica'])]
        private UserLogin $login;
    
        private DateTime $createdAt;
    
        private DateTime $updatedAt;
    
        private Collection $tweets;
    
        private Collection $authors;
    
        private Collection $followers;
    
        private Collection $subscriptionAuthors;
    
        private Collection $subscriptionFollowers;
    
        private ?DateTime $deletedAt = null;
    
        private ?string $avatarLink = null;
    
        private string $password;
    
        #[Groups(['elastica'])]
        private int $age;
    
        private bool $isActive;
    
        private array $roles = [];
    
        private ?string $token = null;
    
        private ?bool $isProtected;
    
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
    
        public function getLogin(): UserLogin
        {
            return $this->login;
        }
    
        public function setLogin(UserLogin $login): void
        {
            $this->login = $login;
        }
    
        public function getCreatedAt(): DateTime {
            return $this->createdAt;
        }
    
        public function setCreatedAt(): void {
            $this->createdAt = DateTime::createFromFormat('U', (string)time());
        }
    
        public function getUpdatedAt(): DateTime {
            return $this->updatedAt;
        }
    
        public function setUpdatedAt(): void {
            $this->updatedAt = DateTime::createFromFormat('U', (string)time());
        }
    
        public function getDeletedAt(): ?DateTime
        {
            return $this->deletedAt;
        }
    
        public function setDeletedAt(): void
        {
            $this->deletedAt = new DateTime();
        }
    
        public function getAvatarLink(): ?string
        {
            return $this->avatarLink;
        }
    
        public function setAvatarLink(?string $avatarLink): void
        {
            $this->avatarLink = $avatarLink;
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
    
        public function getPassword(): string
        {
            return $this->password;
        }
    
        public function setPassword(string $password): void
        {
            $this->password = $password;
        }
    
        public function getAge(): int
        {
            return $this->age;
        }
    
        public function setAge(int $age): void
        {
            $this->age = $age;
        }
    
        public function isActive(): bool
        {
            return $this->isActive;
        }
    
        public function setIsActive(bool $isActive): void
        {
            $this->isActive = $isActive;
        }
    
        /**
         * @return string[]
         */
        public function getRoles(): array
        {
            $roles = $this->roles;
            // guarantee every user at least has ROLE_USER
            $roles[] = RoleEnum::ROLE_USER->value;
    
            return array_unique($roles);
        }
    
        /**
         * @param string[] $roles
         */
        public function setRoles(array $roles): void
        {
            $this->roles = $roles;
        }
    
        public function getToken(): ?string
        {
            return $this->token;
        }
    
        public function setToken(?string $token): void
        {
            $this->token = $token;
        }
    
        public function eraseCredentials(): void
        {
        }
    
        public function getUserIdentifier(): string
        {
            return $this->login;
        }
    
        /**
         * @return Subscription[]
         */
        public function getSubscriptionFollowers(): array
        {
            return $this->subscriptionFollowers->toArray();
        }
    
        /**
         * @return Subscription[]
         */
        public function getSubscriptionAuthors(): array
        {
            return $this->subscriptionAuthors->toArray();
        }
    
        public function isProtected(): bool
        {
            return $this->isProtected ?? false;
        }
    
        public function setIsProtected(bool $isProtected): void
        {
            $this->isProtected = $isProtected;
        }
    
        public function toArray(): array
        {
            return [
                'id' => $this->id,
                'login' => $this->login,
                'avatar' => $this->avatarLink,
                'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
                'updatedAt' => $this->updatedAt->format('Y-m-d H:i:s'),
                'tweets' => array_map(static fn(Tweet $tweet) => $tweet->toArray(), $this->tweets->toArray()),
                'followers' => array_map(
                    static fn(User $user) => ['id' => $user->getId(), 'login' => $user->getLogin()->getValue()],
                    $this->followers->toArray()
                ),
                'authors' => array_map(
                    static fn(User $user) => ['id' => $user->getId(), 'login' => $user->getLogin()->getValue()],
                    $this->authors->toArray()
                ),
                'subscriptionFollowers' => array_map(
                    static fn(Subscription $subscription) => [
                        'subscriptionId' => $subscription->getId(),
                        'userId' => $subscription->getFollower()->getId(),
                        'login' => $subscription->getFollower()->getLogin()->getValue(),
                    ],
                    $this->subscriptionFollowers->toArray()
                ),
                'subscriptionAuthors' => array_map(
                    static fn(Subscription $subscription) => [
                        'subscriptionId' => $subscription->getId(),
                        'userId' => $subscription->getAuthor()->getId(),
                        'login' => $subscription->getAuthor()->getLogin()->getValue(),
                    ],
                    $this->subscriptionAuthors->toArray()
                ),
            ];
        }
    }
    ```
9. В классе `App\Controller\Web\CreateUser\v1\Manager` исправляем метод `create`
    ```php
    public function create(CreateUserDTO $createUserDTO): CreatedUserDTO
    {
        $communicationMethod = $createUserDTO->phone ?? $createUserDTO->email;
        $communicationChannel = $createUserDTO->phone === null ? CommunicationChannelEnum::Email : CommunicationChannelEnum::Phone;
        $createUserModel = $this->modelFactory->makeModel(CreateUserModel::class, $createUserDTO->login, $communicationMethod, $communicationChannel);
        $user = $this->userService->create($createUserModel);

        return new CreatedUserDTO(
            $user->getId(),
            $user->getLogin()->getValue(),
            $user->getAvatarLink(),
            $user->getCreatedAt()->format('Y-m-d H:i:s'),
            $user->getUpdatedAt()->format('Y-m-d H:i:s'),
            $user instanceof PhoneUser ? $user->getPhone() : null,
            $user instanceof EmailUser ? $user->getEmail() : null,
        );
    }
    ```
10. В классе `App\Controller\Web\CreateUser\v2\Manager` исправляем метод `create`
    ```php
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
        $user = $this->userService->create($createUserModel);

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
    ```
11. В классе `App\Controller\Web\UserForm\v1\Manager` исправляем метод `getFormData`
    ```php
    public function getFormData(Request $request, ?User $user = null): array
    {
        $isNew = $user === null;
        $formData = $isNew ? null : new CreateUserDTO(
            $user->getLogin()->getValue(),
            $user instanceof EmailUser ? $user->getEmail() : null,
            $user instanceof PhoneUser ? $user->getPhone() : null,
            $user->getPassword(),
            $user->getAge(),
            $user->isActive(),
        );
        $form = $this->formFactory->create(UserType::class, $formData, ['isNew' => $isNew]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var CreateUserDTO $createUserDTO */
            $createUserDTO = $form->getData();
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
            );
            $user = $this->userService->create($createUserModel);
        }

        return [
            'form' => $form,
            'isNew' => $isNew,
            'user' => $user,
        ];
    }
    ```
12. В классе `App\Domain\ApiPlatform\State\UserProviderDecorator` исправляем метод `provide`
    ```php
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /** @var User $user */
        $user = $this->itemProvider->provide($operation, $uriVariables, $context);

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
    ```
13. В классе `App\Infrastructure\Repository\TweetRepositoryCacheDecorator` исправляем метод `getTweetsPaginated`
    ```php
    public function getTweetsPaginated(int $page, int $perPage): array
    {
        return $this->cache->get(
            $this->getCacheKey($page, $perPage),
            function (ItemInterface $item) use ($page, $perPage) {
                $tweets = $this->tweetRepository->getTweetsPaginated($page, $perPage);
                $tweetModels = array_map(
                    static fn (Tweet $tweet): TweetModel => new TweetModel(
                        $tweet->getId(),
                        $tweet->getAuthor()->getLogin()->getValue(),
                        $tweet->getAuthor()->getId(),
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
    ```
14. В классе `App\Domain\Service\TweetService` исправляем метод `postTweet`
    ```php
    public function postTweet(User $author, string $text): void
    {
        $tweet = new Tweet();
        $tweet->setAuthor($author);
        $tweet->setText($text);
        $author->addTweet($tweet);
        $this->tweetRepository->create($tweet);
        $tweetModel = new TweetModel(
            $tweet->getId(),
            $tweet->getAuthor()->getLogin()->getValue(),
            $tweet->getAuthor()->getId(),
            $tweet->getText(),
            $tweet->getCreatedAt()
        );
        $this->publishTweetBus->sendPublishTweetMessage($tweetModel);
    }
    ```
15. Исправляем класс `App\Domain\Service\UserService`
    ```php
    <?php
    
    namespace App\Domain\Service;
    
    use App\Domain\Entity\EmailUser;
    use App\Domain\Entity\PhoneUser;
    use App\Domain\Entity\User;
    use App\Domain\Event\CreateUserEvent;
    use App\Domain\Event\UserIsCreatedEvent;
    use App\Domain\Model\CreateUserModel;
    use App\Domain\ValueObject\CommunicationChannelEnum;
    use App\Domain\ValueObject\UserLogin;
    use App\Infrastructure\Repository\UserRepository;
    use DateInterval;
    use Symfony\Component\EventDispatcher\EventDispatcherInterface;
    use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
    
    class UserService
    {
        public function __construct(
            private readonly UserRepository $userRepository,
            private readonly UserPasswordHasherInterface $userPasswordHasher,
            private readonly EventDispatcherInterface $eventDispatcher,
        ) {
        }
    
        public function create(CreateUserModel $createUserModel): User
        {
            $user = match($createUserModel->communicationChannel) {
                CommunicationChannelEnum::Email => (new EmailUser())->setEmail($createUserModel->communicationMethod),
                CommunicationChannelEnum::Phone => (new PhoneUser())->setPhone($createUserModel->communicationMethod),
            };
            $user->setLogin(UserLogin::fromString($createUserModel->login));
            $user->setPassword($this->userPasswordHasher->hashPassword($user, $createUserModel->password));
            $user->setAge($createUserModel->age);
            $user->setIsActive($createUserModel->isActive);
            $user->setRoles($createUserModel->roles);
            $this->userRepository->create($user);
            $this->eventDispatcher->dispatch(new UserIsCreatedEvent($user->getId(), $user->getLogin()));
    
            return $user;
        }
    
        public function createWithPhone(string $login, string $phone): User
        {
            $user = new PhoneUser();
            $user->setLogin(UserLogin::fromString($login));
            $user->setPhone($phone);
            $this->userRepository->create($user);
    
            return $user;
        }
    
        public function createWithEmail(string $login, string $email): User
        {
            $user = new EmailUser();
            $user->setLogin(UserLogin::fromString($login));
            $user->setEmail($email);
            $this->userRepository->create($user);
    
            return $user;
        }
    
        public function refresh(User $user): void
        {
            $this->userRepository->refresh($user);
        }
    
        public function subscribeUser(User $author, User $follower): void
        {
            $this->userRepository->subscribeUser($author, $follower);
        }
    
        /**
         * @return User[]
         */
        public function findUsersByLogin(string $login): array
        {
            return $this->userRepository->findUsersByLogin($login);
        }
    
        /**
         * @return User[]
         */
        public function findUsersByLoginWithCriteria(string $login): array
        {
            return $this->userRepository->findUsersByLoginWithCriteria($login);
        }
    
        public function updateUserLogin(int $userId, string $login): ?User
        {
            $user = $this->userRepository->find($userId);
            if (!($user instanceof User)) {
                return null;
            }
            $this->userRepository->updateLogin($user, $login);
    
            return $user;
        }
    
        public function findUsersByLoginWithQueryBuilder(string $login): array
        {
            return $this->userRepository->findUsersByLoginWithQueryBuilder($login);
        }
    
        public function updateUserLoginWithQueryBuilder(int $userId, string $login): ?User
        {
            $user = $this->userRepository->find($userId);
            if (!($user instanceof User)) {
                return null;
            }
            $this->userRepository->updateUserLoginWithQueryBuilder($user->getId(), $login);
            $this->userRepository->refresh($user);
    
            return $user;
        }
    
        public function updateUserLoginWithDBALQueryBuilder(int $userId, string $login): ?User
        {
            $user = $this->userRepository->find($userId);
            if (!($user instanceof User)) {
                return null;
            }
            $this->userRepository->updateUserLoginWithDBALQueryBuilder($user->getId(), $login);
            $this->userRepository->refresh($user);
    
            return $user;
        }
    
        public function findUserWithTweetsWithQueryBuilder(int $userId): array
        {
            return $this->userRepository->findUserWithTweetsWithQueryBuilder($userId);
        }
    
        public function findUserWithTweetsWithDBALQueryBuilder(int $userId): array
        {
            return $this->userRepository->findUserWithTweetsWithDBALQueryBuilder($userId);
        }
    
        public function removeById(int $userId): bool
        {
            $user = $this->userRepository->find($userId);
            if ($user instanceof User) {
                $this->userRepository->remove($user);
    
                return true;
            }
    
            return false;
        }
    
        public function removeByIdInFuture(int $userId, DateInterval $dateInterval): void
        {
            $user = $this->userRepository->find($userId);
            if ($user instanceof User) {
                $this->userRepository->removeInFuture($user, $dateInterval);
            }
        }
    
        /**
         * @return User[]
         */
        public function findUsersByLoginWithDeleted(string $login): array
        {
            return $this->userRepository->findUsersByLoginWithDeleted($login);
        }
    
        public function findUserById(int $id): ?User
        {
            return $this->userRepository->find($id);
        }
    
        /**
         * @return User[]
         */
        public function findAll(): array
        {
            return $this->userRepository->findAll();
        }
    
        public function remove(User $user): void
        {
            $this->userRepository->remove($user);
        }
    
        public function updateLogin(User $user, string $login): void
        {
            $this->userRepository->updateLogin($user, $login);
        }
    
        public function updateAvatarLink(User $user, string $avatarLink): void
        {
            $this->userRepository->updateAvatarLink($user, $avatarLink);
        }
    
        public function processFromForm(User $user): void
        {
            $this->userRepository->create($user);
        }
    
        public function findUserByLogin(string $login): ?User
        {
            $users = $this->userRepository->findUsersByLogin($login);
    
            return $users[0] ?? null;
        }
    
        public function updateUserToken(string $login): ?string
        {
            $user = $this->findUserByLogin($login);
            if ($user === null) {
                return null;
            }
    
            return $this->userRepository->updateUserToken($user);
        }
    
        public function findUserByToken(string $token): ?User
        {
            return $this->userRepository->findUserByToken($token);
        }
    
        public function clearUserToken(string $login): void
        {
            $user = $this->findUserByLogin($login);
            if ($user !== null) {
                $this->userRepository->clearUserToken($user);
            }
        }
    
        /**
         * @return User[]
         */
        public function findUsersByQuery(string $query, int $perPage, int $page): array
        {
            return $this->userRepository->findUsersByQuery($query, $perPage, $page);
        }
    }
    ```
16. В классе `App\Infrastructure\Repository\UserRepository` исправляем метод `updateLogin`
    ```php
    public function updateLogin(User $user, string $login): void
    {
        $user->setLogin(UserLogin::fromString($login));
        $this->flush();
    }
    ```
17. В классе `App\Domain\ApiPlatform\GraphQL\Resolver\UserCollectionResolver` исправляем метод `__invoke`
    ```php
    public function __invoke(iterable $collection, array $context): iterable
    {
        /** @var User $user */
        foreach ($collection as $user) {
            if ($user->isProtected()) {
                $user->setLogin(UserLogin::fromString(self::MASK));
                $user->setPassword(self::MASK);
            }
        }

        return $collection;
    }
    ```
18. В классе `App\Domain\ApiPlatform\GraphQL\Resolver\UserResolver` исправляем метод `__invoke`
    ```php
    public function __invoke($item, array $context): User
    {
        if (isset($context['args']['_id'])) {
            $item = $this->userService->findUserById($context['args']['_id']);
        } elseif (isset($context['args']['login'])) {
            $item = $this->userService->findUserByLogin($context['args']['login']);
        }

        if ($item->isProtected()) {
            $item->setLogin(UserLogin::fromString(self::MASK));
            $item->setPassword(self::MASK);
        }

        return $item;
    }
    ```
19. В классе `App\Controller\Web\GetUser\v1\Controller` убираем атрибут `IsGranted` с метода `__invoke`
20. Выполняем запрос Add user v2 из Postman-коллекции v10. Видим, что запись в БД создалась.
21. Выполняем запрос Get users из Postman-коллекции v10, видим ошибку
22. Очищаем кэш метаданных Doctrine командой `php bin/console doctrine:cache:clear-metadata`
23. Ещё раз выполняем запрос Get users из Postman-коллекции v10, видим созданного пользователя.
