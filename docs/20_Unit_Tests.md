# Unit-тестирование

Запускаем контейнеры командой `docker-compose up -d`

## Устанавливаем PHPUnit bridge

1. Заходим в контейнер командой `docker exec -it php sh`. Дальнейшие команды выполняем из контейнера
2. Добавляем пакеты `symfony/phpunit-bridge` и `mockery/mockery` в **dev-режиме**
3. Выполняем команду `./vendor/bin/simple-phpunit --migrate-configuration`
4. Исправляем в composer.json секцию `autoload-dev`
    ```json
    "autoload-dev": {
        "psr-4": {
            "UnitTests\\": "tests/unit"
        }
    },
    ```
5. Исправляем файл `phpunit.xml.dist`, добавляя в раздел `<php>` новую переменную
    ```xml
    <server name="SYMFONY_DEPRECATIONS_HELPER" value="disabled" />
    ```

## Пишем тест с мок-сервисом

1. Добавляем класс `UnitTests\Service\UserServiceTest`
    ```php
    <?php
    
    namespace UnitTests\Service;
    
    use App\Domain\Entity\EmailUser;
    use App\Domain\Entity\PhoneUser;
    use App\Domain\Model\CreateUserModel;
    use App\Domain\Service\UserService;
    use App\Domain\ValueObject\CommunicationChannelEnum;
    use App\Infrastructure\Repository\UserRepository;
    use Generator;
    use Mockery;
    use PHPUnit\Framework\TestCase;
    use Symfony\Component\EventDispatcher\EventDispatcherInterface;
    use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
    
    class UserServiceTest extends TestCase
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
            $userRepository->shouldIgnoreMissing();
            $userPasswordHasher = Mockery::mock(UserPasswordHasherInterface::class);
            $userPasswordHasher->shouldReceive('hashPassword')
                ->andReturn(self::PASSWORD_HASH);
            $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
            $eventDispatcher->shouldIgnoreMissing();
    
            return new UserService($userRepository, $userPasswordHasher, $eventDispatcher);
        }
    }
    ```
2. Запускаем тесты командой `./vendor/bin/simple-phpunit`, видим 2 ошибки
3. В интерфейсе `App\Domain\Entity\EntityInterface` исправляем декларацию метода `getId`
    ```php
    public function getId(): ?int;
    ```
4. В классе `App\Domain\Entity\User` исправляем метод `getId`
    ```php
    public function getId(): ?int
    {
        return $this->id;
    }
    ```
5. Ещё раз запускаем тесты, видим дальнейшую ошибку.

## Добавляем поведение к мок-методу

1. Отменяем правки в интерфейсе `App\Domain\Entity\EntityInteface` и классе `App\Domain\Entity\User`
2. В классе `UnitTests\Service\UserServiceTest` исправляем метод `prepareUserService`
    ```php
    private function prepareUserService(): UserService
    {
        $userRepository = Mockery::mock(UserRepository::class);
        $userRepository->shouldReceive('create')->with(
            Mockery::on(static function($user) {
                $user->setId(1);

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
    ```

## Добавляем время в тест

1. В классе `UnitTests\Service\UserServiceTest` исправляем метод `testCreate`
    ```php
    /**
     * @dataProvider createTestCases
     */
    public function testCreate(CreateUserModel $createUserModel, array $expectedData): void
    {
        $userService = $this->prepareUserService();
        $expectedData['createdAt'] = (new DateTime())->format('Y-m-d H:i:s');
        $expectedData['updatedAt'] = (new DateTime())->format('Y-m-d H:i:s');
        sleep(1);

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
            'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $user->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];
        static::assertSame($expectedData, $actualData);
    }
    ```
2. Запускаем тесты командой `./vendor/bin/simple-phpunit`, видим 2 ошибки
3. В классе `UnitTests\Service\UserServiceTest` исправляем метод `prepareUserService`
    ```php
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
    ```
4. Запускаем тесты командой `./vendor/bin/simple-phpunit`, всё ещё видим 2 ошибки
5. В классе `App\Domain\Entity\User` исправляем методы `setCreatedAt` и `setUpdatedAt`
    ```php
    #[ORM\PrePersist]
    public function setCreatedAt(): void {
        $this->createdAt = DateTime::createFromFormat('U', (string)time());
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function setUpdatedAt(): void {
        $this->updatedAt = DateTime::createFromFormat('U', (string)time());
    }
    ```
6. В классе `UnitTests\Service\UserServiceTest` исправляем метод `testCreate`
    ```php
    /**
     * @dataProvider createTestCases
     * @group time-sensitive
     */
    public function testCreate(CreateUserModel $createUserModel, array $expectedData): void
    {
        $userService = $this->prepareUserService();
        $expectedData['createdAt'] = DateTime::createFromFormat('U', (string)time())->format('Y-m-d H:i:s');
        $expectedData['updatedAt'] = DateTime::createFromFormat('U', (string)time())->format('Y-m-d H:i:s');
        sleep(1);

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
            'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $user->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];
        static::assertSame($expectedData, $actualData);
    }
    ```
7. Ещё раз запускаем тесты командой `./vendor/bin/simple-phpunit`, видим ошибку только на первом кейсе

## Выполняем тесты параллельно

1. В классе `UnitTests\Service\UserServiceTest` исправляем метод `testCreate`
    ```php
    /**
     * @dataProvider createTestCases
     */
    public function testCreate(CreateUserModel $createUserModel, array $expectedData): void
    {
        $userService = $this->prepareUserService();
        sleep(5);

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
    ```
2. Копируем класс `UnitTests\Service\UserServiceTest` в `UnitTests\Service2\UserServiceTest`
3. Копируем файл `phpunit.xml.dist` в `tests/unit/Service` и в `tests/unit/Service2`
4. В обоих файлах исправляем содержимое тэга `<testsuites>`
    ```xml
    <testsuite name="Service Test Suite">
      <directory>.</directory>
    </testsuite>
    ```
5. Запускаем тесты командой `./vendor/bin/simple-phpunit`, видим, что тесты выполняются параллельно за 21-22 секунды
6. Запускаем тесты командой `./vendor/bin/simple-phpunit tests`, видим, что выполняется два раздельных запуска по 10-11
   секунд
