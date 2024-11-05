# Интеграционное тестирование

Запускаем контейнеры командой `docker-compose up -d`

# Устанавливаем Codeception и переносим unit-тест сервиса в Codeception

1. Заходим в контейнер командой `docker exec -it php sh`. Дальнейшие команды выполняем из контейнера
2. Переименовываем директорию `tests/unit` в `tests/phpunit`
3. Устанавливаем пакеты `codeception/codeception`, `codeception/module-phpbrowser`, `codeception/module-symfony`,
   `codeception/module-doctrine`, `codeception/module-asserts`, `codeception/module-datafactory`,
   `codeception/module-rest` **в dev-режиме**
4. Исправляем файл `.env.test`, заменяя значение переменной `SYMFONY_DEPRECATIONS_HELPER` на `disabled`
5. Выполняем команду `vendor/bin/codecept build`
6. В файле `composer.json` исправляем секцию `autoload-dev`
    ```json
    "autoload-dev": {
        "psr-4": {
            "UnitTests\\": "tests/Unit"
        }
    },
    ```
7. Выполняем команду `composer dump-autoload`
8. Создаём класс `UnitTests\Service\UserServiceTest`
    ```php
    <?php
    
    namespace UnitTests\Service;
    
    use App\Domain\Entity\EmailUser;
    use App\Domain\Entity\PhoneUser;
    use App\Domain\Model\CreateUserModel;
    use App\Domain\Service\UserService;
    use App\Domain\ValueObject\CommunicationChannelEnum;
    use App\Infrastructure\Repository\UserRepository;
    use Codeception\Test\Unit;
    use Generator;
    use Mockery;
    use Symfony\Component\EventDispatcher\EventDispatcherInterface;
    use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
    
    class UserServiceTest extends Unit
    {
        private const PASSWORD_HASH = 'my_hash';
        private const DEFAULT_AGE = 18;
        private const DEFAULT_IS_ACTIVE = true;
        private const DEFAULT_ROLES = ['ROLE_USER'];
    
        /**
         * @dataProvider createTestCases
         */
        public function testCreate(CreateUserModel $createUserModel, array $expectedData): void
        {
            $userService = $this->prepareUserService();
    
            $user = $userService->create($createUserModel);
    
            $actualData = [
                'class' => get_class($user),
                'login' => $user->getLogin(),
                'email' => ($user instanceof EmailUser) ? $user->getEmail() : null,
                'phone' => ($user instanceof PhoneUser) ? $user->getPhone() : null,
                'passwordHash' => $user->getPassword(),
                'age' => $user->getAge(),
                'isActive' => $user->isActive(),
                'roles' => $user->getRoles(),
            ];
            static::assertSame($expectedData, $actualData);
        }
    
        protected function createTestCases(): Generator
        {
            yield [
                new CreateUserModel(
                    'someLogin',
                    'somePhone',
                    CommunicationChannelEnum::Phone
                ),
                [
                    'class' => PhoneUser::class,
                    'login' => 'someLogin',
                    'email' => null,
                    'phone' => 'somePhone',
                    'passwordHash' => self::PASSWORD_HASH,
                    'age' => self::DEFAULT_AGE,
                    'isActive' => self::DEFAULT_IS_ACTIVE,
                    'roles' => self::DEFAULT_ROLES,
                ]
            ];
    
            yield [
                new CreateUserModel(
                    'otherLogin',
                    'someEmail',
                    CommunicationChannelEnum::Email
                ),
                [
                    'class' => EmailUser::class,
                    'login' => 'otherLogin',
                    'email' => 'someEmail',
                    'phone' => null,
                    'passwordHash' => self::PASSWORD_HASH,
                    'age' => self::DEFAULT_AGE,
                    'isActive' => self::DEFAULT_IS_ACTIVE,
                    'roles' => self::DEFAULT_ROLES,
                ]
            ];
        }
    
        private function prepareUserService(): UserService
        {
            $userRepository = Mockery::mock(UserRepository::class);
            $userRepository->shouldReceive('create')->with(
                Mockery::on(static function($user) {
                    $user->setId(1);
                    $user->setCreatedAt();
                    $user->setUpdatedAt();
    
                    return true;
                })
            );
            $userPasswordHasher = Mockery::mock(UserPasswordHasherInterface::class);
            $userPasswordHasher->shouldReceive('hashPassword')
                ->andReturn(self::PASSWORD_HASH);
            $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
            $eventDispatcher->shouldIgnoreMissing();
    
            return new UserService($userRepository, $userPasswordHasher, $eventDispatcher);
        }
    }
    ```
9. Запускаем тесты командой `./vendor/bin/codecept run Unit`, видим, что они проходят

## Делаем тест команды интеграционным

1. В классе `App\Controller\Cli\AddFollowersCommand` убираем `sleep(100)` в методе `execute`
2. В файле `composer.json` исправляем секцию `autoload-dev`
    ```json
    "autoload-dev": {
        "psr-4": {
            "UnitTests\\": "tests/Unit",
            "FunctionalTests\\": "tests/Functional"
        }
    },
    ```
3. Выполняем команду `composer dump-autoload`
4. В файле `tests/Functional.suite.yml` раскомментируем модуль `Doctrine2`
    ```yaml
    - Doctrine:
        depends: Symfony
        cleanup: true
    ```
5. Перегенерируем акторы командой `vendor/bin/codecept build`
6. Создаём класс `FunctionalTests\Controller\Cli\AddFollowersCommandCest`
    ```php
    <?php
    
    namespace FunctionalTests\Controller\Cli;
    
    use App\Domain\Entity\PhoneUser;
    use App\Tests\Support\FunctionalTester;
    use Codeception\Example;
    
    class AddFollowersCommandCest
    {
        private const COMMAND = 'followers:add';
    
        /**
         * @dataProvider executeDataProvider
         */
        public function testExecuteReturnsResult(FunctionalTester $I, Example $example): void
        {
            $authorId = $I->haveInRepository(PhoneUser::class, [
                'login' => 'admin',
                'password' => 'password',
                'age' => 18,
                'isActive' => true,
                'roles' => [],
            ]);
            $params = ['authorId' => $authorId, '--login' => $example['login']];
            $inputs = $example['followersCount'] === null ? ["\n"] : [$example['followersCount']."\n"];
            $output = $I->runSymfonyConsoleCommand(self::COMMAND, $params, $inputs);
            $I->assertStringEndsWith($example['expected'], $output);
        }
    
        protected function executeDataProvider(): array
        {
            return [
                'positive' => ['followersCount' => 20, 'login' => 'login', 'expected' => "20 followers were created\n"],
                'zero' => ['followersCount' => 0, 'login' => 'other_login', 'expected' => "0 followers were created\n"],
                'default' => ['followersCount' => null, 'login' => 'login3', 'expected' => "10 followers were created\n"],
                'negative' => ['followersCount' => -1, 'login' => 'login_too', 'expected' => "Count should be positive integer\n"],
            ];
        }
    }
    ```
7. Запускаем тесты командой `./vendor/bin/codecept run Functional`, видим ошибку подключения к БД
8. Выполняем команды для создания тестовой БД
    ```shell
    php bin/console doctrine:database:create --env=test
    php bin/console doctrine:migrations:migrate --env=test
    ```
9. Ещё раз запускаем тесты командой `./vendor/bin/codecept run Functional`, видим 1 ошибку
 
## Исправляем тест команды

1. Исправляем класс `FunctionalTests\Controller\Cli\AddFollowersCommandCest`
    ```php
    <?php
    
    namespace FunctionalTests\Controller\Cli;
    
    use App\Domain\Entity\PhoneUser;
    use App\Tests\Support\FunctionalTester;
    use Codeception\Example;
    
    class AddFollowersCommandCest
    {
        private const COMMAND = 'followers:add';
    
        /**
         * @dataProvider executeDataProvider
         */
        public function testExecuteReturnsResult(FunctionalTester $I, Example $example): void
        {
            $authorId = $I->haveInRepository(PhoneUser::class, [
                'login' => 'admin',
                'password' => 'password',
                'age' => 18,
                'isActive' => true,
                'roles' => [],
                'phone' => '+1234567890'
            ]);
            $params = ['authorId' => $authorId, '--login' => $example['login']];
            $inputs = $example['followersCount'] === null ? ["\n"] : [$example['followersCount']."\n"];
            $output = $I->runSymfonyConsoleCommand(self::COMMAND, $params, $inputs, $example['exitCode']);
            $I->assertStringEndsWith($example['expected'], $output);
        }
    
        protected function executeDataProvider(): array
        {
            return [
                'positive' => ['followersCount' => 20, 'login' => 'login', 'expected' => "20 followers were created\n", 'exitCode' => 0],
                'zero' => ['followersCount' => 0, 'login' => 'other_login', 'expected' => "0 followers were created\n", 'exitCode' => 0],
                'default' => ['followersCount' => null, 'login' => 'login3', 'expected' => "10 followers were created\n", 'exitCode' => 0],
                'negative' => ['followersCount' => -1, 'login' => 'login_too', 'expected' => "Count should be positive integer\n", 'exitCode' => 1],
            ];
        }
    }
    ```
2. Ещё раз запускаем тесты командой `./vendor/bin/codecept run Functional`, видим успешное выполнение

## Используем DataFactory

1. В файле `composer.json` исправляем секцию `autoload-dev`
    ```json
    "autoload-dev": {
        "psr-4": {
            "UnitTests\\": "tests/Unit",
            "FunctionalTests\\": "tests/Functional",
            "Support\\": "tests/Support"
        }
    },
    ```
2. Выполняем команду `composer dump-autoload`
3. Создаём класс `Support\Helper\Factories`
    ```php
    <?php
    
    namespace Support\Helper;
    
    use App\Domain\Entity\PhoneUser;
    use Codeception\Module;
    use Codeception\Module\DataFactory;
    use League\FactoryMuffin\Faker\Facade;
    
    class Factories extends Module
    {
        public function _beforeSuite($settings = []): void
        {
            /** @var DataFactory $factory */
            $factory = $this->getModule('DataFactory');
    
            $factory->_define(
                PhoneUser::class,
                [
                    'login' => Facade::text(20),
                    'password' => Facade::text(20),
                    'age' => Facade::randomNumber(2),
                    'roles' => [],
                    'isActive' => true,
                    'phone' => '+0'.Facade::randomNumber(9, true)(),
                ]
            );
        }
    }
    ```
4. В файле `tests/Functional.suite.yml` подключаем модули `DataFactory` и `Factories`
    ```yaml
    - DataFactory:
          depends: Doctrine
          cleanup: true
    - \Support\Helper\Factories
    ```
5. Перегенерируем акторы командой `./vendor/bin/codecept build`
6. Исправляем в классе `FunctionalTests\Controller\Cli\AddFollowersCommandCest` метод `testExecuteReturnsResult`
    ```php
    /**
     * @dataProvider executeDataProvider
     */
    public function testExecuteReturnsResult(FunctionalTester $I, Example $example): void
    {
        /** @var PhoneUser $author */
        $author = $I->have(PhoneUser::class);
        $params = ['authorId' => $author->getId(), '--login' => $example['login']];
        $inputs = $example['followersCount'] === null ? ["\n"] : [$example['followersCount']."\n"];
        $output = $I->runSymfonyConsoleCommand(self::COMMAND, $params, $inputs, $example['exitCode']);
        $I->assertStringEndsWith($example['expected'], $output);
    }
    ```
7. Запускаем тесты командой `./vendor/bin/codecept run Functional`, видим успешный результат

## Используем DataFactory для работы со связанными сущностями

1. В классе `App\Domain\Entity\User` добавим метод `getSubscriptionAuthors`
    ```php
    /**
     * @return Subscription[]
     */
    public function getSubscriptionAuthors(): array
    {
        return $this->subscriptionAuthors->toArray();
    }
    ```
2. В классе `App\Infrastructure\Repository\TweetRepository` добавляем метод `getTweetsForAuthorIds`
    ```php
    /**
     * @param int[] $authorIds
     * @return Tweet[]
     */
    public function getTweetsForAuthorIds(array $authorIds, int $count): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('t')
            ->from(Tweet::class, 't')
            ->where($qb->expr()->in('IDENTITY(t.author)', ':authorIds'))
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($count);

        $qb->setParameter('authorIds', $authorIds);

        return $qb->getQuery()->getResult();
    }
    ```
3. Исправляем класс `App\Domain\Service\FeedService`
    ```php
    <?php
    
    namespace App\Domain\Service;
    
    use App\Domain\Bus\PublishTweetBusInterface;
    use App\Domain\Bus\SendNotificationBusInterface;
    use App\Domain\DTO\SendNotificationDTO;
    use App\Domain\Entity\EmailUser;
    use App\Domain\Entity\Subscription;
    use App\Domain\Entity\Tweet;
    use App\Domain\Entity\User;
    use App\Domain\Model\TweetModel;
    use App\Domain\ValueObject\CommunicationChannelEnum;
    use App\Infrastructure\Repository\FeedRepository;
    use App\Infrastructure\Repository\TweetRepository;
    
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
4. Исправляем класс `Support\Helper\Factories`
   ```php
   <?php
    
   namespace Support\Helper;
    
   use App\Domain\Entity\PhoneUser;
   use App\Domain\Entity\Subscription;
   use App\Domain\Entity\Tweet;
   use Codeception\Module;
   use Codeception\Module\DataFactory;
   use League\FactoryMuffin\Faker\Facade;
    
   class Factories extends Module
   {
       public function _beforeSuite($settings = []): void
       {
           /** @var DataFactory $factory */
           $factory = $this->getModule('DataFactory');
    
           $factory->_define(
               PhoneUser::class,
               [
                   'login' => Facade::text(20),
                   'password' => Facade::text(20),
                   'age' => Facade::randomNumber(2),
                   'roles' => [],
                   'isActive' => true,
                   'phone' => '+0'.Facade::randomNumber(9, true)(),
               ]
           );
           $factory->_define(
               Tweet::class,
               [
                   'text' => Facade::text(100),
               ]
           );
           $factory->_define(Subscription::class, []);
       }
   }
   ```
5. Создаём класс `FunctionalTests\Service\FeedServiceCest`
    ```php
    <?php
    
    namespace FunctionalTests\Service;
    
    use App\Domain\Entity\PhoneUser;
    use App\Domain\Entity\Subscription;
    use App\Domain\Entity\Tweet;
    use App\Domain\Service\FeedService;
    use App\Tests\Support\FunctionalTester;
    use Codeception\Example;
    
    class FeedServiceCest
    {
        private const PRATCHETT_AUTHOR = 'Terry Pratchett';
        private const TOLKIEN_AUTHOR = 'John R.R. Tolkien';
        private const CARROLL_AUTHOR = 'Lewis Carrol';
        private const TOLKIEN1_TEXT = 'Hobbit';
        private const PRATCHETT1_TEXT = 'Colours of Magic';
        private const TOLKIEN2_TEXT = 'Lord of the Rings';
        private const PRATCHETT2_TEXT = 'Soul Music';
        private const CARROL1_TEXT = 'Alice in Wonderland';
        private const CARROL2_TEXT = 'Through the Looking-Glass';
    
        public function _before(FunctionalTester $I)
        {
            $pratchett = $I->have(PhoneUser::class, ['login' => self::PRATCHETT_AUTHOR]);
            $tolkien = $I->have(PhoneUser::class, ['login' => self::TOLKIEN_AUTHOR]);
            $carroll = $I->have(PhoneUser::class, ['login' => self::CARROLL_AUTHOR]);
            $I->have(Tweet::class, ['author' => $pratchett, 'text' => self::PRATCHETT1_TEXT]);
            sleep(1);
            $I->have(Tweet::class, ['author' => $pratchett, 'text' => self::PRATCHETT2_TEXT]);
            sleep(1);
            $I->have(Tweet::class, ['author' => $tolkien, 'text' => self::TOLKIEN1_TEXT]);
            sleep(1);
            $I->have(Tweet::class, ['author' => $tolkien, 'text' => self::TOLKIEN2_TEXT]);
            sleep(1);
            $I->have(Tweet::class, ['author' => $carroll, 'text' => self::CARROL1_TEXT]);
            sleep(1);
            $I->have(Tweet::class, ['author' => $carroll, 'text' => self::CARROL2_TEXT]);
        }
    
        /**
         * @dataProvider getFeedFromTweetsDataProvider
         */
        public function testGetFeedFromTweetsReturnsCorrectResult(FunctionalTester $I, Example $example): void
        {
            $follower = $I->have(PhoneUser::class);
            foreach ($example['authors'] as $authorLogin) {
                $author = $I->grabEntityFromRepository(PhoneUser::class, ['login' => $authorLogin]);
                $I->have(Subscription::class, ['author' => $author, 'follower' => $follower]);
            }
            /** @var FeedService $feedService */
            $feedService = $I->grabService(FeedService::class);
    
            $feed = $feedService->getFeedWithoutMaterialization($follower, $example['tweetsCount']);
    
            $I->assertSame($example['expected'], array_map(static fn(Tweet $tweet) => $tweet->getText(), $feed));
        }
    
        protected function getFeedFromTweetsDataProvider(): array
        {
            return [
                'all authors, all tweets' => [
                    'authors' => [self::TOLKIEN_AUTHOR, self::CARROLL_AUTHOR, self::PRATCHETT_AUTHOR],
                    'tweetsCount' => 6,
                    'expected' => [
                        self::CARROL2_TEXT,
                        self::CARROL1_TEXT,
                        self::TOLKIEN2_TEXT,
                        self::TOLKIEN1_TEXT,
                        self::PRATCHETT2_TEXT,
                        self::PRATCHETT1_TEXT,
                    ]
                ]
            ];
        }
    }
    ```
6. Запускаем тесты командой `./vendor/bin/codecept run Functional`, видим ошибку

## Исправляем тест

1. В классе `FunctionalTests\Service\FeedServiceCest` исправляем метод `testGetFeedFromTweetsReturnsCorrectResult`
    ```php
    /**
     * @dataProvider getFeedFromTweetsDataProvider
     */
    public function testGetFeedFromTweetsReturnsCorrectResult(FunctionalTester $I, Example $example): void
    {
        $follower = $I->have(PhoneUser::class);
        foreach ($example['authors'] as $authorLogin) {
            $author = $I->grabEntityFromRepository(PhoneUser::class, ['login' => $authorLogin]);
            $I->have(Subscription::class, ['author' => $author, 'follower' => $follower]);
        }
        /** @var FeedService $feedService */
        $feedService = $I->grabService(FeedService::class);
        $I->clearEntityManager();
        $follower = $I->grabEntityFromRepository(PhoneUser::class, ['id' => $follower->getId()]);

        $feed = $feedService->getFeedWithoutMaterialization($follower, $example['tweetsCount']);

        $I->assertSame($example['expected'], array_map(static fn(Tweet $tweet) => $tweet->getText(), $feed));
    }
    ```
2. Запускаем тесты командой `./vendor/bin/codecept run Functional`, видим успешный результат

## Добавляем системный тест

1. Исправляем секцию `enabled` в файле `tests/Acceptance.suite.yml` (отключаем модуль `PhpBrowser` и добавляем модуль
   `REST`)
    ```yaml
    enabled:
        - REST:
            url: http://nginx:80
            depends: PhpBrowser
            part: Json
    ```
2. Перегенерируем акторы командой `vendor/bin/codecept build`
3. В файле `composer.json` исправляем секцию `autoload-dev`
    ```json
    "autoload-dev": {
        "psr-4": {
            "UnitTests\\": "tests/Unit",
            "FunctionalTests\\": "tests/Functional",
            "AcceptanceTests\\": "tests/Acceptance",
            "Support\\": "tests/Support"
        }
    },
    ```
4. Выполняем команду `composer dump-autoload`
5. Добавляем класс `AcceptanceTests\Api\v1\UserCest`
    ```php
    <?php
    
    namespace AcceptanceTests\Web\CreateUser\v2;
    
    use App\Tests\Support\AcceptanceTester;
    use Codeception\Util\HttpCode;
    
    class ControllerCest
    {
        public function testAddUserAction(AcceptanceTester $I): void
        {
            $I->haveHttpHeader('Content-Type', 'application/json');
            $I->sendPost('/api/v2/user', [
                'login' => 'my_user',
                'password' => 'my_password',
                'roles' => ['ROLE_USER'],
                'age' => 23,
                'isActive' => true,
                'phone' => '+0123456789',
            ]);
            $I->canSeeResponseCodeIs(HttpCode::OK);
            $I->canSeeResponseMatchesJsonType(['id' => 'integer:>0']);
        }
    }
    ```
6. Запускаем тесты командой `./vendor/bin/codecept run Acceptance`, видим, что всё работает

## Включаем аутентификацию для тестового окружения

1. Исправляем файл `config/packages/security.yaml`
    ```yaml
    security:
        password_hashers:
            App\Domain\Entity\User: auto
            Symfony\Component\Security\Core\User\InMemoryUser: plaintext

        providers:
            users_in_memory:
                memory:
                    users:
                        admin:
                            password: 'my_pass'
                            roles: 'ROLE_ADMIN'
                        user:
                            password: 'other_pass'
                            roles: 'ROLE_USER'

        firewalls:
            main:
                http_basic:
                lazy: true
                provider: users_in_memory

        access_control:
            - { path: ^/api/v2/user, roles: ROLE_ADMIN, methods: [POST] }
       ```
2. Запускаем тесты командой `./vendor/bin/codecept run Acceptance`, видим ошибку

## Исправляем тест

1. В файл `tests/_support/AcceptanceTester.php` добавляем два метода
    ```
    public function amAdmin(): void
    {
        $this->amHttpAuthenticated('admin', 'my_pass');
    }
    
    public function amUser(): void
    {
        $this->amHttpAuthenticated('user', 'other_pass');
    }
    ```
2. Перегенерируем акторы командой `vendor/bin/codecept build`
3. Исправляем класс `AcceptanceTests\Web\CreateUser\v1\ControllerCest`
    ```php
    <?php
    
    namespace AcceptanceTests\Web\CreateUser\v2;
    
    use App\Tests\Support\AcceptanceTester;
    use Codeception\Util\HttpCode;
    
    class ControllerCest
    {
        public function testAddUserActionAsAdmin(AcceptanceTester $I): void
        {
            $I->amAdmin();
            $I->haveHttpHeader('Content-Type', 'application/json');
            $I->sendPost('/api/v2/user', $this->getMethodParams());
            $I->canSeeResponseCodeIs(HttpCode::OK);
            $I->canSeeResponseMatchesJsonType(['id' => 'integer:>0']);
        }
    
        public function testAddUserActionAsUser(AcceptanceTester $I): void
        {
            $I->amUser();
            $I->haveHttpHeader('Content-Type', 'application/json');
            $I->sendPost('/api/v2/user', $this->getMethodParams());
            $I->canSeeResponseCodeIs(HttpCode::FORBIDDEN);
        }
        
        private function getMethodParams(): array
        {
            return [
                'login' => 'my_user2',
                'password' => 'my_password',
                'roles' => ['ROLE_USER'],
                'age' => 23,
                'isActive' => true,
                'phone' => '+0123456789',
            ];
        }
    }
    ```
4. Запускаем тесты командой `./vendor/bin/codecept run Acceptance`, видим, что они проходят   
