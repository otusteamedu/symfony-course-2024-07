# REST-приложения и FOSRestBundle

Запускаем контейнеры командой `docker-compose up -d`

## Устанавливам API Platform

1. Заходим в контейнер `php` командой `docker exec -it php-1 sh`. Дальнейшие команды выполняются из контейнера
2. Устанавливаем пакет `api-platform/core`
3. В файле `config/packages/api_platform.yaml`
   1. удаляем параметр `keep_legacy_inflector`
   2. добавляем секцию `api_plaftorm.mapping`
       ```yaml
       mapping:
           paths:
               - '%kernel.project_dir%/src/Domain/Entity'
       ```
4. В файле `config/routes/api_platform.yaml` меняем префикс API
    ```yaml
    api_platform:
        resource: .
        type: api_platform
        prefix: /api-platform
    ```
5. В файле `config/packages/security.yaml` в секцию `security.firewalls.main` добавляем `security: false`
6. Удаляем директорию `src/ApiResource`
7. К классу `App\Domain\Entity\PhoneUser` добавляем атрибут `#[ApiResource]`
8. Переходим в браузере по адресу `http://localhost:7777/api-platform`

## Проверяем работоспособность API

1. Выполняем запрос Add user v2 из Postman-коллекции v5, чтобы в БД появился пользователь
2. Выполняем запрос на получение коллекции PhoneUser в интерфейсе API-документации API Platform, видим полные данные
   нашего пользователя
3. Выполняем запрос на создание PhoneUser в интерфейсе API-документации API Platform с payload из запроса Add user v2
   из Postman-коллекции v5 с заменой логина на уникальный, видим успешный ответ и созданного в БД пользователя, но
   пароль не зашифрован

## Добавляем процессор для сохранения сущности

1. Добавляем класс `App\Domain\ApiPlatform\State\UserProcessor`
    ```php
    <?php
    
    namespace App\Domain\ApiPlatform\State;
    
    use ApiPlatform\Metadata\Operation;
    use ApiPlatform\State\ProcessorInterface;
    use App\Domain\Entity\User;
    use App\Infrastructure\Repository\UserRepository;
    use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
    
    /**
     * @implements ProcessorInterface<User, User|void>
     */
    class UserProcessor implements ProcessorInterface
    {
        public function __construct(
            private readonly UserPasswordHasherInterface $userPasswordHasher,
            private readonly UserRepository $userRepository,
        ) {
        }
    
        /**
         * @param User $data
         * @return User|void
         */
        public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
        {
            $data->setPassword($this->userPasswordHasher->hashPassword($data, $data->getPassword()));
            $this->userRepository->create($data);
    
            return $data;
        }
    }
    ``` 
2. К классу `App\Domain\Entity\PhoneUser` добавляем атрибут `#[Post(processor: UserProcessor::class)]`
3. Ещё раз выполняем запрос на создание PhoneUser в интерфейсе API-документации API Platform с payload из запроса Add
   user v2 из Postman-коллекции v5 с изменённым на уникальный логином, видим успешный ответ и зашифрованный пароль в БД.
4. Выполняем запрос Get token из Postman-коллекции v5 с реквизитами добавленного пользователя, видим успешный ответ.

## Переходим на DTO при сохранении

1. В классе `App\Domain\Entity\PhoneUser` исправляем атрибут
    ```php
    #[Post(input: CreateUserDTO::class, output: CreatedUserDTO::class, processor: UserProcessor::class)]
    ```
2. Исправляем класс `App\Domain\ApiPlatform\State\UserProcessor`
    ```php
    <?php
    
    namespace App\Domain\ApiPlatform\State;
    
    use ApiPlatform\Metadata\Operation;
    use ApiPlatform\State\ProcessorInterface;
    use App\Controller\Web\CreateUser\v2\Input\CreateUserDTO;
    use App\Controller\Web\CreateUser\v2\Manager;
    use App\Controller\Web\CreateUser\v2\Output\CreatedUserDTO;
    
    /**
     * @implements ProcessorInterface<CreateUserDTO, CreatedUserDTO|void>
     */
    class UserProcessor implements ProcessorInterface
    {
        public function __construct(private readonly Manager $manager)
        {
        }
    
        /**
         * @param CreateUserDTO $data
         * @return CreatedUserDTO|void
         */
        public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
        {
            return $this->manager->create($data);
        }
    }
    ```
3. Ещё раз выполняем запрос на создание PhoneUser в интерфейсе API-документации API Platform с payload из запроса Add
   user v2 из Postman-коллекции v5 с изменённым на уникальный логином, видим успешный ответ и зашифрованный пароль в БД.

## Делаем общий ресурс для обоих типов пользователей

1. Переносим атрибуты `ApiResource` и `Post` из класса `App\Domain\Entity\PhoneUser` в `App\Domain\Entity\User`
2. Обновляем страницу в браузере.
3. Выполняем запросы на создание User в интерфейсе API-документации API Platform с email и телефоном, видим успешно
   созданные записи.

## Добавляем декоратор для провайдера для использования исходящего DTO

1. Добавляем класс `App\Domain\ApiPlatform\State\UserProviderDecorator`
    ```php
    <?php
    
    namespace App\Domain\ApiPlatform\State;
    
    use ApiPlatform\Metadata\Operation;
    use ApiPlatform\State\ProviderInterface;
    use App\Controller\Web\CreateUser\v2\Output\CreatedUserDTO;
    use App\Domain\Entity\EmailUser;
    use App\Domain\Entity\PhoneUser;
    use App\Domain\Entity\User;
    use Symfony\Component\DependencyInjection\Attribute\Autowire;
    
    /**
     * @implements ProviderInterface<CreatedUserDTO>
     */
    class UserProviderDecorator implements ProviderInterface
    {
        public function __construct(
            #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
            private readonly ProviderInterface $itemProvider,
        ) {
        }
    
        public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
        {
            /** @var User $user */
            $user = $this->itemProvider->provide($operation, $uriVariables, $context);
            
            return new CreatedUserDTO(
                $user->getId(),
                $user->getLogin(),
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
2. К классу `App\Domain\Entity\User` добавляем атрибут
    ```php
    #[Get(output: CreatedUserDTO::class, provider: UserProviderDecorator::class)]
    ```
3. Обновляем страницу в браузере.
4. Выполняем запросы на получение User в интерфейсе API-документации API Platform с id пользователей с телефоном и
   email, видим успешные ответы

## Получаем JSON Schema

1. Добавляем класс `App\Controller\Web\GetJsonSchema\v1\Manager`
    ```php
    <?php
    
    namespace App\Controller\Web\GetJsonSchema\v1;
    
    use ApiPlatform\JsonSchema\SchemaFactoryInterface;
    
    class Manager
    {
        public function __construct(private readonly SchemaFactoryInterface $jsonSchemaFactory)
        {
        }
    
        public function getJsonSchemaAction(string $resource): array
        {
            $className = 'App\\Domain\\Entity\\'.ucfirst($resource);
            $schema = $this->jsonSchemaFactory->buildSchema($className);
    
            return json_decode(json_encode($schema), true);
        }
    }
    ```
2. Добавляем класс `App\Controller\Web\GetJsonSchema\v1\Controller`
    ```php
    <?php
    
    namespace App\Controller\Web\GetJsonSchema\v1;
    
    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\HttpKernel\Attribute\AsController;
    use Symfony\Component\Routing\Attribute\Route;
    
    #[AsController]
    class Controller
    {
        public function __construct(private readonly Manager $manager) {
        }
    
        #[Route(path: 'api/v1/get-json-schema/{resource}', methods: ['GET'])]
        public function __invoke(string $resource): Response
        {
            return new JsonResponse($this->manager->getJsonSchemaAction($resource));
        }
    }
    ```
3. Выполняем запрос Get JSON Schema из Postman-коллекции v5

## Добавляем аутентификацию с помощью JWT

1. В файле `config/packages/security.yaml`
   1. удаляем `security: false` из `security.firewalls.main`
   2. добавляем секцию `security.firewalss.api-platform` перед `main`
       ```yaml
       apiPlatform:
           pattern: ^/api-platform$
           security: false      
       ```
2. В классе `App\Application\Security\AuthService` исправляем метод `getToken`
    ```php
    /**
     * @throws JWTEncodeFailureException
     */
    public function getToken(string $login): string
    {
        $user = $this->userService->findUserByLogin($login);
        $refreshToken = $this->userService->updateUserToken($login);
        $tokenData = [
            'username' => $login,
            'roles' => $user?->getRoles() ?? [],
            'exp' => time() + $this->tokenTTL,
            'refresh_token' => $refreshToken,
        ];

        return $this->jwtEncoder->encode($tokenData);
    }
    ```
3. В файле `config/api-platform.yaml` добавляем секцию `swagger`
    ```yaml
    swagger:
        api_keys:
            JWT:
                name: Authorization
                type: header
    ```
4. Выполняем запрос Get token из Postman-коллекции v5.
5. Перезагружаем страницу в браузере, нажимаем на кнопку Authorize и вводим `Bearer TOKEN`, где `TOKEN` – токен из
   предыдущего шага
6. Выполняем запрос Get JSON Schema из Postman-коллекции v11, видим ошибку 401
7. Выполняем запрос на получение User в интерфейсе API-документации API Platform с id существующего пользователя, видим
   успешный ответ
