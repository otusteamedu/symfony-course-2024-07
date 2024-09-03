# Контроллеры и маршрутизация

Запускаем контейнеры командой `docker-compose up -d`

## Добавляем CRUD-методы для работы с пользователем

1. Удаляем класс `App\Controller\WorldController`
2. Добавляем класс `App\Controller\Web\CreateUser\v1\Manager`
    ```php
    <?php
    
    namespace App\Controller\Web\CreateUser\v1;
    
    use App\Domain\Entity\User;
    use App\Domain\Service\UserService;
    
    class Manager
    {
        public function __construct(private readonly UserService $userService)
        {
        }
    
        public function create(string $login, ?string $phone = null, ?string $email = null): ?User
        {
            if ($phone !== null) {
                return $this->userService->createWithPhone($login, $phone);
            }
            
            if ($email !== null) {
                return $this->userService->createWithEmail($login, $email);
            }
            
            return null;
        }
    }
    ```
3. Добавляем класс `App\Controller\Web\CreateUser\v1\Controller`
    ```php
    <?php
    
    namespace App\Controller\Web\CreateUser\v1;
    
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
    
        #[Route(path: 'api/v1/user', methods: ['POST'])]
        public function __invoke(Request $request): Response
        {
            $login = $request->request->get('login');
            $phone = $request->request->get('phone');
            $email = $request->request->get('email');
            $user = $this->manager->create($login, $phone, $email);
            if ($user === null) {
                return new JsonResponse(null, Response::HTTP_BAD_REQUEST);
            }
    
            return new JsonResponse($user->toArray());
        }
    }    
    ```
4. В классе `App\Infrastructure\Repository\UserRepository` добавляем метод `findAll`
    ```php
    /**
     * @return User[]
     */
    public function findAll(): array
    {
        return $this->entityManager->getRepository(User::class)->findAll();
    }
    ```
5. В классе `App\Domain\Service\UserService` добавляем методы `findUserById` и `findAll`
    ```php
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
    ```
6. Добавляем класс `App\Controller\Web\GetUser\v1\Manager`
    ```php
    <?php
    
    namespace App\Controller\Web\GetUser\v1;
    
    use App\Domain\Entity\User;
    use App\Domain\Service\UserService;
    
    class Manager
    {
        public function __construct(private readonly UserService $userService)
        {
        }
    
        public function getUserById(int $userId): ?User
        {
            return $this->userService->findUserById($userId);
        }

        /**
         * @return User[]
         */
        public function getAllUsers(): array
        {
            return $this->userService->findAll();
        }
    }    
    ```
7. Добавляем класс `App\Controller\Web\GetUser\v1\Controller`
    ```php
    <?php
    
    namespace App\Controller\Web\GetUser\v1;
    
    use App\Domain\Entity\User;
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
    
        #[Route(path: 'api/v1/user', methods: ['GET'])]
        public function __invoke(Request $request): Response
        {
            $userId = $request->query->get('id');
            if ($userId === null) {
                return new JsonResponse(array_map(static fn (User $user): array => $user->toArray(), $this->manager->getAllUsers()));
            }
            $user = $this->manager->getUserById($userId);
            if ($user instanceof User) {
                return new JsonResponse($user->toArray());
            }
    
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }
    }
    ```
8. Добавляем класс `App\Controller\Web\UpdateUserLogin\v1\Manager`
    ```php
    <?php
    
    namespace App\Controller\Web\UpdateUserLogin\v1;
    
    use App\Domain\Entity\User;
    use App\Domain\Service\UserService;
    
    class Manager
    {
        public function __construct(private readonly UserService $userService)
        {
        }
    
        public function updateUserLogin(int $userId, string $login): bool
        {
            $user = $this->userService->updateUserLogin($userId, $login);
            
            return $user instanceof User;
        }
    }
    ```
9. Добавляем класс `App\Controller\Web\UpdateUserLogin\v1\Controller`
    ```php
    <?php
    
    namespace App\Controller\Web\UpdateUserLogin\v1;
    
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
    
        #[Route(path: 'api/v1/user', methods: ['PATCH'])]
        public function __invoke(Request $request): Response
        {
            $userId = $request->request->get('id');
            $login = $request->request->get('login');
            $result = $this->manager->updateUserLogin($userId, $login);
    
            if ($result) {
                return new JsonResponse(['success' => true]);
            }
    
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }
    }
    ```
10. В классе `App\Domain\Service\UserService` исправляем метод `removeById`
    ```php
    public function removeById(int $userId): bool
    {
        $user = $this->userRepository->find($userId);
        if ($user instanceof User) {
            $this->userRepository->remove($user);
           
            return true;
        }
       
        return false;
    }
    ```
11. Добавляем класс `App\Controller\Web\DeleteUser\v1\Manager`
    ```php
    <?php
    
    namespace App\Controller\Web\DeleteUser\v1;
    
    use App\Domain\Service\UserService;
    
    class Manager
    {
        public function __construct(private readonly UserService $userService)
        {
        }
    
        public function deleteUserById(int $userId): bool
        {
            return $this->userService->removeById($userId);
        }
    }
    ```
12. Добавляем класс `App\Controller\Web\DeleteUser\v1\Controller`
    ```php
    <?php
    
    namespace App\Controller\Web\DeleteUser\v1;
    
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
    
        #[Route(path: 'api/v1/user', methods: ['DELETE'])]
        public function __invoke(Request $request): Response
        {
            $userId = $request->query->get('id');
            $result = $this->manager->deleteUserById($userId);
            if ($result) {
                return new JsonResponse(['success' => true]);
            }
    
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }
    }
    ```
13. Заходим в контейнер `php` командой `docker exec -it php sh`. Дальнейшие команды выполняются из контейнера
14. Выполняем команду `php bin/console debug:router`, видим список наших endpoint'ов из контроллера
15. Выполняем запрос Add user из Postman-коллекции, видим, что пользователь добавился
16. Выполняем запрос Delete user из Postman-коллекции с id из результата предыдущего запроса, видим, что пользователь
    удалился

## Добавляем инъекцию id в метод контроллера

1. В классе `App\Controller\Web\DeleteUser\v1\Controller` исправляем метод `__invoke`
    ```php
    #[Route(path: 'api/v1/user/{id}', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function __invoke(int $id): Response
    {
        $result = $this->manager->deleteUserById($id);
        if ($result) {
            return new JsonResponse(['success' => true]);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }
    ```
2. Ещё раз выполняем запрос Add user из Postman-коллекции, чтобы создать пользователя
3. Выполняем запрос Delete user by id из Postman-коллекции с id из результата предыдущего запроса, видим, что
   пользователь удалился

## Исправляем запрос Patch user

1. Ещё раз выполняем запрос Add user из Postman-коллекции, чтобы создать пользователя
2. Пробуем отправить запрос Patch user из Postman-коллекции для созданного в предыдущем запросе пользователя, видим
   ошибку 500
3. Переносим в Postman-коллекции в запросе Patch user параметры из тела в строку запроса
4. Исправляем в классе `App\Controller\Web\UpdateUserLogin\v1\Controller` метод `__invoke`
    ```php
    #[Route(path: 'api/v1/user', methods: ['PATCH'])]
    public function __invoke(Request $request): Response
    {
        $userId = $request->query->get('id');
        $login = $request->query->get('login');
        $result = $this->manager->updateUserLogin($userId, $login);

        if ($result) {
            return new JsonResponse(['success' => true]);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }
    ```
5. Ещё раз пробуем отправить запрос Patch user из Postman-коллекции, логин обновляется

## Делаем инъекцию сущности в метод контроллера по id

1. Устанавливаем пакет `symfony/expression-language` командой `composer require symfony/expression-language`
   (понадобится для использования параметра `expr` атрибута `MapEntity`)
2. В классе `App\Domain\Service\UserService` добавляем метод `remove`
    ```php
    public function remove(User $user): void
    {
        $this->userRepository->remove($user);
    }
    ```
3. В классе `App\Controller\Web\DeleteUser\v1\Manager` добавляем метод `deleteUser`
    ```php
    public function deleteUser(User $user): void
    {
        $this->userService->remove($user);
    }
    ```
4. Исправляем класс `App\Controller\Web\DeleteUser\v1\Controller`
    ```php
    <?php
    
    namespace App\Controller\Web\DeleteUser\v1;
    
    use App\Domain\Entity\User;
    use Symfony\Bridge\Doctrine\Attribute\MapEntity;
    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\HttpKernel\Attribute\AsController;
    use Symfony\Component\Routing\Attribute\Route;
    
    #[AsController]
    class Controller
    {
        public function __construct(private readonly Manager $manager) {
        }
    
        #[Route(path: 'api/v1/user/{id}', requirements: ['id' => '\d+'], methods: ['DELETE'])]
        public function __invoke(#[MapEntity(id: 'id')] User $user): Response
        {
            $this->manager->deleteUser($user);
    
            return new JsonResponse(['success' => true]);
        }
    }
    ```
5. Выполняем запрос Delete user из Postman-коллекции с несуществующим id, видим ошибку 404
6. Выполняем запрос Delete user из Postman-коллекции с id существующего пользователя, видим, что он удалился

## Делаем инъекцию сущности в метод контроллера по полю login

1. В классе `User` добавляем атрибут
    ```php
    #[ORM\UniqueConstraint(name: 'user__login__uniq', columns: ['login'], options: ['where' => '(deleted_at IS NULL)'])]
    ```
2. Выполняем команду `php bin/console doctrine:migrations:diff`
3. Проверяем сгенерированную миграцию и применяем её с помощью команды `php bin/console doctrine:migrations:migrate`
4. Добавляем класс `App\Controller\Web\GetUserByLogin\v1\Controller`
    ```php
    <?php
    
    namespace App\Controller\Web\GetUserByLogin\v1;
    
    use App\Domain\Entity\User;
    use Symfony\Bridge\Doctrine\Attribute\MapEntity;
    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\HttpKernel\Attribute\AsController;
    use Symfony\Component\Routing\Attribute\Route;
    
    #[AsController]
    class Controller
    {
        #[Route(path: '/api/v1/get-user-by-login/{login}', methods: ['GET'])]
        public function getUserByLoginAction(#[MapEntity(mapping: ['login' => 'login'])] User $user): Response
        {
            return new JsonResponse(['user' => $user->toArray()], Response::HTTP_OK);
        }
    }
    ```
5. Выполняем запрос Get user by login из Postman-коллекции с несуществующим логином, видим ошибку 404
6. Выполняем запрос Get user by login из Postman-коллекции с существующим логином, видим успешный ответ

## Делаем инъекцию сущности в метод контроллера с помощью expression language

1. В классе `App\Domain\Service\UserService` добавляем метод `updateLogin`
    ```php
    public function updateLogin(User $user, string $login): void
    {
        $this->userRepository->updateLogin($user, $login);
    }
    ```
2. Исправляем класс `App\Controller\Web\UpdateUserLogin\v1\Manager`
    ```php
    <?php
    
    namespace App\Controller\Web\UpdateUserLogin\v1;
    
    use App\Domain\Entity\User;
    use App\Domain\Service\UserService;
    
    class Manager
    {
        public function __construct(private readonly UserService $userService)
        {
        }
    
        public function updateLogin(User $user, string $login): void
        {
            $this->userService->updateLogin($user, $login);
        }
    }    
    ```
3. Исправляем класс `App\Controller\Web\UpdateUserLogin\v1\Controller`
    ```php
    <?php
    
    namespace App\Controller\Web\UpdateUserLogin\v1;
    
    use App\Domain\Entity\User;
    use Symfony\Bridge\Doctrine\Attribute\MapEntity;
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
    
        #[Route(path: 'api/v1/user/{id}', methods: ['PATCH'])]
        public function __invoke(#[MapEntity(expr: 'repository.find(id)')] User $user, Request $request): Response
        {
            $login = $request->query->get('login');
            $this->manager->updateLogin($user, $login);
    
            return new JsonResponse(['success' => true]);
        }
    }
    ```
4. Выполняем запрос Patch user by id из Postman-коллекции с несуществующим шв, видим ошибку 404
5. Выполняем запрос Patch user by id из Postman-коллекции с существующим логином, видим успешный ответ
