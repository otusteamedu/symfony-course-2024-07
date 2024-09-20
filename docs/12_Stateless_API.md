# Stateless API

Запускаем контейнеры командой `docker-compose up -d`

## Добавляем генерацию токена

1. Входим в контейнер командой `docker exec -it php sh`. Дальнейшие команды выполняются из контейнера
2. В файле `config/packages/security.yaml` добавляем в секцию `firewalls` после секции `dev`
    ```yaml
    token:
        pattern: ^/api/v1/get-token
        security: false
    ``` 
3. В класс `App\Domain\Entity\User` добавляем поле `$token` и стандартные геттер/сеттер для него
    ```php
    #[ORM\Column(type: 'string', length: 32, unique: true, nullable: true)]
    private ?string $token = null;

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): void
    {
        $this->token = $token;
    }
    ```
4. Генерируем миграцию командой `php bin/console doctrine:migrations:diff`
5. Проверяем сгенерированную миграцию и применяем её с помощью команды `php bin/console doctrine:migrations:migrate`
6. В классе `App\Infrastructure\Repository\UserRepository` добавляем новый метод `updateUserToken`
    ```php 
    public function updateUserToken(User $user): string
    {
        $token = base64_encode(random_bytes(20));
        $user->setToken($token);
        $this->flush();
        
        return $token;
    }
    ```
7. В классе `App\Domain\Service\UserService` добавляем новые методы `findUserByLogin` и `updateUserToken`
    ```php
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
    ```
8. Добавляем класс `App\Application\Security\AuthService`
    ```php
    <?php
    
    namespace App\Application\Security;
    
    use App\Domain\Service\UserService;
    use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
    
    class AuthService
    {
        public function __construct(
            private readonly UserService $userService,
            private readonly UserPasswordHasherInterface $passwordHasher,
        ) {
        }
    
        public function isCredentialsValid(string $login, string $password): bool
        {
            $user = $this->userService->findUserByLogin($login);
            if ($user === null) {
                return false;
            }
    
            return $this->passwordHasher->isPasswordValid($user, $password);
        }
    
        public function getToken(string $login): ?string
        {
            return $this->userService->updateUserToken($login);
        }
    }
    ```
9. Добавляем класс `App\Controller\Exception\UnauthorizedException`
    ```php
    <?php
    
    namespace App\Controller\Exception;
    
    use Exception;
    use Symfony\Component\HttpFoundation\Response;
    
    class UnauthorizedException extends Exception implements HttpCompliantExceptionInterface
    {
        public function getHttpCode(): int
        {
            return Response::HTTP_UNAUTHORIZED;
        }
    
        public function getHttpResponseBody(): string
        {
            return 'Unauthorized';
        }
    }
    ```
10. Добавляем класс `App\Controller\Web\GetToken\v1\Manager`
    ```php
    <?php
    
    namespace App\Controller\Web\GetToken\v1;
    
    use App\Application\Security\AuthService;
    use App\Controller\Exception\AccessDeniedException;
    use App\Controller\Exception\UnauthorizedException;
    use Symfony\Component\HttpFoundation\Request;
    
    class Manager
    {
        public function __construct(private readonly AuthService $authService)
        {
        }
    
        /**
         * @throws AccessDeniedException
         * @throws UnauthorizedException
         */
        public function getToken(Request $request): string
        {
            $user = $request->getUser();
            $password = $request->getPassword();
            if (!$user || !$password) {
                throw new UnauthorizedException();
            }
            if (!$this->authService->isCredentialsValid($user, $password)) {
                throw new AccessDeniedException();
            }
    
            return $this->authService->getToken($user);
        }
    } 
    ```
11. Добавляем класс `App\Controller\Web\GetToken\v1\Controller`
    ```php
    <?php
    
    namespace App\Controller\Web\GetToken\v1;
    
    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\HttpKernel\Attribute\AsController;
    use Symfony\Component\Routing\Attribute\Route;
    
    #[AsController]
    class Controller
    {
        public function __construct(private readonly Manager $manager) {
        }
    
        #[Route(path: 'api/v1/get-token', methods: ['POST'])]
        public function __invoke(Request $request): Response
        {
            return new JsonResponse(['token' => $this->manager->getToken($request)]);
        }
    } 
    ```
12. Выполняем запрос Add user v2 из Postman-коллекции v4, чтобы получить в БД пользователя
13. Выполняем запрос Get token из Postman-коллекции v4 без авторизации, получаем ошибку 401
14. Выполняем запрос Get token из Postman-коллекции v4 с неверными реквизитами, получаем ошибку 403
15. Выполняем запрос Get token из Postman-коллекции v4 с верными реквизитами, получаем токен. Проверяем, что в БД токен
    тоже сохранился.

## Добавляем аутентификатор с помощью токена

1. В классе `App\Infrastructure\Repository\UserRepository` добавляем метод `findUserByToken`
    ```php 
    public function findUserByToken(string $token): ?User
    {
        /** @var User|null $user */
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['token' => $token]); 
        
        return $user;
    }
    ```
2. В классе `App\Domain\Service\UserService` добавляем метод `findUserByToken`
    ```php
    public function findUserByToken(string $token): ?User
    {
        return $this->userRepository->findUserByToken($token);
    }
    ```
3. Добавляем класс `App\Application\Security\ApiTokenAuthenticator`
    ```php
    <?php
    
    namespace App\Application\Security;
    
    use App\Controller\Exception\AccessDeniedException;
    use App\Controller\Exception\UnauthorizedException;
    use App\Domain\Service\UserService;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
    use Symfony\Component\Security\Core\Exception\AuthenticationException;
    use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
    use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
    use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
    use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
    
    class ApiTokenAuthenticator extends AbstractAuthenticator
    {
        public function __construct(private readonly UserService $userService)
        {
        }
    
        public function supports(Request $request): ?bool
        {
            return true;
        }
    
        public function authenticate(Request $request): Passport
        {
            $authorization = $request->headers->get('Authorization');
            $token = str_starts_with($authorization, 'Bearer ') ? substr($authorization, 7) : null;
            if ($token === null) {
                throw new UnauthorizedException();
            }
    
            return new SelfValidatingPassport(
                new UserBadge($token, fn($token) => $this->userService->findUserByToken($token))
            );
        }
    
        public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
        {
            return null;
        }
    
        public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
        {
            throw new AccessDeniedException();
        }
    }
    ```
4. В файле `config/packages/security.yaml` меняем содержимое секции `firewalls.main`
    ```yaml
    main:
        lazy: true
        stateless: true
        custom_authenticator: App\Application\Security\ApiTokenAuthenticator
    ```
5. Выполняем запрос Get user из Postman-коллекции v4 без авторизации, получаем ошибку 401
6. Выполняем запрос Get token из Postman-коллекции v4, полученный токен заносим в Bearer-авторизацию запроса Get user и
   выполняем его, видим ошибку 403
7. Добавляем пользователю в БД роль `ROLE_ADMIN` и проверяем, что запрос Get user сразу же возвращает успешный ответ

## Добавляем JWT-аутентификатор

1. Устанавливаем пакет `lexik/jwt-authentication-bundle`
2. Генерируем ключи, используя passphrase из файла `.env` командами
    ```shell
    mkdir config/jwt
    openssl genrsa -out config/jwt/private.pem -aes256 4096
    openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem
    chmod 777 config/jwt -R
    ```
3. В файл `.env` добавляем параметр
    ```shell
    JWT_TTL_SEC=3600
    ```
4. В файл `config/packages/lexik_jwt_authentication.yaml` добавляем строку
    ```yaml
    token_ttl: '%env(JWT_TTL_SEC)%'
    ```
5. В классе `App\Application\Security\AuthService`
    1. Добавляем зависимость от `Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface` и целочисленный параметр `tokenTTL`
        ```php
        public function __construct(
            private readonly UserService $userService,
            private readonly UserPasswordHasherInterface $passwordHasher,
            private readonly JWTEncoderInterface $jwtEncoder,
            private readonly int $tokenTTL,
        ) {
        }
        ```         
    2. Исправляем метод `getToken`
        ```php
        /**
         * @throws JWTEncodeFailureException
         */
        public function getToken(string $login): string
        {
            $tokenData = ['username' => $login, 'exp' => time() + $this->tokenTTL];
   
            return $this->jwtEncoder->encode($tokenData);
        }
        ```
6. В файле `config/services.yaml` добавляем новый сервис
    ```yaml
    App\Application\Security\AuthService:
        arguments:
            $tokenTTL: '%env(JWT_TTL_SEC)%'
    ```
7. Добавляем класс `App\Application\Security\AuthUser`
    ```php
    <?php
    
    namespace App\Application\Security;
    
    use Symfony\Component\Security\Core\User\UserInterface;
    
    class AuthUser implements UserInterface
    {
        private string $username;
        
        /** @var string[] */
        private array $roles;
    
        public function __construct(array $credentials)
        {
            $this->username = $credentials['username'];
            $this->roles = array_unique(array_merge($credentials['roles'] ?? [], ['ROLE_USER']));
        }
    
        /**
         * @return string[]
         */
        public function getRoles(): array
        {
            return $this->roles;
        }
    
        public function getPassword(): string
        {
            return '';
        }
    
        public function getUserIdentifier(): string
        {
            return $this->username;
        }
    
        public function eraseCredentials(): void
        {
        }
    }
    ```
8. Добавляем класс `App\Application\Security\JwtAuthenticator`
    ```php
    <?php
    
    namespace App\Application\Security;
    
    use App\Controller\Exception\AccessDeniedException;
    use App\Controller\Exception\UnauthorizedException;
    use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
    use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\AuthorizationHeaderTokenExtractor;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
    use Symfony\Component\Security\Core\Exception\AuthenticationException;
    use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
    use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
    use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
    use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
    
    class JwtAuthenticator extends AbstractAuthenticator
    {
        public function __construct(private readonly JWTEncoderInterface $jwtEncoder)
        {
        }
    
        public function supports(Request $request): ?bool
        {
            return true;
        }
    
        public function authenticate(Request $request): Passport
        {
            $extractor = new AuthorizationHeaderTokenExtractor('Bearer', 'Authorization');
            $token = $extractor->extract($request);
            if ($token === null) {
                throw new UnauthorizedException();
            }
            $tokenData = $this->jwtEncoder->decode($token);
            if (!isset($tokenData['username'])) {
                throw new UnauthorizedException();
            }
    
            return new SelfValidatingPassport(
                new UserBadge($tokenData['username'], fn() => new AuthUser($tokenData))
            );
        }
    
        public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
        {
            return null;
        }
    
        public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
        {
            throw new AccessDeniedException();
        }
    }
    ```
9. В файле `config/packages/security.yaml` заменяем в секции `firewalls.main` значение поля `custom_authenticator` на
   `App\Application\Security\JwtAuthenticator`
10. Выполняем запрос Get user из Postman-коллекции v4 со старым токеном, получаем ошибку 500 с сообщением Invalid JWT
    Token
11. Выполняем запрос Get token из Postman-коллекции v4, полученный токен заносим в Bearer-авторизацию запроса Get user и
    выполняем его, получаем ошибку 403

## Исправляем получение JWT

1. В классе `App\Application\Security\AuthService` исправляем метод `getToken`
     ```php
     public function getToken(string $login): string
     {
         $user = $this->userService->findUserByLogin($login);
         $tokenData = [
             'username' => $login,
             'roles' => $user?->getRoles() ?? [],
             'exp' => time() + $this->tokenTTL,
         ];

         return $this->jwtEncoder->encode($tokenData);
     }
     ```
2. Перевыпускаем токен запросом Get token из Postman-коллекции v4, полученный токен заносим в Bearer-авторизацию
   запроса Get user. Выполняем запрос Get user, получаем результат
3. Удалям у пользователя в БД роль `ROLE_ADMIN`
4. Выполняем запрос Get user и видим результат, хоть роль и была удалена в БД
5. Ещё раз перевыпускаем токен запросом Get token из Postman-коллекции v4, полученный токен заносим в
   Bearer-авторизацию запроса Get user и выполняем его, получаем ошибку 403

## Добавляем реэмиссию JWT

1. В классе `App\Application\Security\AuthService` исправляем метод `getToken`
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
            'exp' => time(),
            'refresh_token' => $refreshToken,
        ];

        return $this->jwtEncoder->encode($tokenData);
    }
    ```
2. В классе `App\Infrastructure\Repository\UserRepository` добавляем метод `clearUserToken`
    ```php
    public function clearUserToken(User $user): void
    {
        $user->setToken(null);
        $this->flush();
    }
    ```
3. В классе `App\Domain\Service\UserService` добавляем метод `clearUserToken`
    ```php
    public function clearUserToken(string $login): void
    {
        $user = $this->findUserByLogin($login);
        if ($user !== null) {
            $this->userRepository->clearUserToken($user);
        }
    }
    ```
4. Добавляем класс `App\Controller\Web\RefreshToken\v1\Manager`
    ```php
    <?php
    
    namespace App\Controller\Web\RefreshToken\v1;
    
    use App\Application\Security\AuthService;
    use App\Domain\Service\UserService;use Symfony\Component\Security\Core\User\UserInterface;
    use Symfony\Component\Security\Core\User\UserInterface;
    
    class Manager
    {
        public function __construct(
            private readonly AuthService $authService,
            private readonly UserService $userService,
        ) {
        }
    
        public function refreshToken(UserInterface $user): string
        {
            $this->userService->clearUserToken($user->getUserIdentifier());
        
            return $this->authService->getToken($user->getUserIdentifier());
        }
    }
    ```
5. Добавляем класс `App\Controller\Web\RefreshToken\v1\Controller`
     ```php
     <?php
     
     namespace App\Controller\Web\RefreshToken\v1;
     
     use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
     use Symfony\Component\HttpFoundation\JsonResponse;
     use Symfony\Component\HttpFoundation\Request;
     use Symfony\Component\HttpFoundation\Response;
     use Symfony\Component\Routing\Attribute\Route;
     
     class Controller extends AbstractController
     {
         public function __construct(private readonly Manager $manager) {
         }
     
         #[Route(path: 'api/v1/refresh-token', methods: ['POST'])]
         public function __invoke(Request $request): Response
         {
             return new JsonResponse(['token' => $this->manager->refreshToken($this->getUser())]);
         }
     }
     ```
6. В файле `config/packages/security.yaml` добавляем в секцию `firewalls` после секции `dev`
    ```yaml
    refreshToken:
        pattern: ^/api/v1/refresh-token
        stateless: true
        custom_authenticator: App\Application\Security\ApiTokenAuthenticator
    ``` 
7. В классе `App\Controller\Exception\UnauthorizedException` исправляем метод `getHttpResponseBody`
    ```php
    public function getHttpResponseBody(): string
    {
        return empty($this->getMessage()) ? 'Unauthorized' : $this->getMessage();
    }
    ```
8. В классе `App\Application\Security\JwtAuthenticator` исправляем метод `authenticate`
    ```php
    public function authenticate(Request $request): Passport
    {
        $extractor = new AuthorizationHeaderTokenExtractor('Bearer', 'Authorization');
        $token = $extractor->extract($request);
        if ($token === null) {
            throw new UnauthorizedException();
        }
        try {
            $tokenData = $this->jwtEncoder->decode($token);
        } catch (JWTDecodeFailureException $exception) {
            $message = $exception->getReason() === JWTDecodeFailureException::EXPIRED_TOKEN ? 'Expired token' : ''; 
            throw new UnauthorizedException($message);
        }
        if (!isset($tokenData['username'])) {
            throw new UnauthorizedException();
        }

        return new SelfValidatingPassport(
            new UserBadge($tokenData['username'], fn() => new AuthUser($tokenData))
        );
    }
    ```
9. Перевыпускаем токен запросом Get token из Postman-коллекции v4, полученный токен заносим в Bearer-авторизацию
   запроса Get user. Выполняем запрос Get user, получаем ошибку с кодом 401 и текстом `Expired token`
10. Выполняем запрос Refresh token из Postman-коллекции v4 с токеном из поля `refresh_token` в JWT, получаем новый JWT
11. Ещё раз выполняем запрос Refresh token с тем же токеном, получаем ошибку 403
