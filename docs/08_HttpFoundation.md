# Компонент HttpFoundation

Запускаем контейнеры командой `docker-compose up -d`

## Добавляем ссылку на аватар к пользователю

1. В классе `App\Domain\Entity\User`
   1. добавляем новое поле, геттер и сеттер
       ```php
       #[ORM\Column(type: 'string', nullable: true)]
       private ?string $avatarLink = null;

       public function getAvatarLink(): ?string
       {
           return $this->avatarLink;
       }

       public function setAvatarLink(?string $avatarLink): void
       {
           $this->avatarLink = $avatarLink;
       }
       ```
   2. исправляем метод `toArray`
       ```php
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
       ```
2. Выполняем команду `php bin/console doctrine:migrations:diff`
3. Проверяем сгенерированную миграцию и применяем её с помощью команды `php bin/console doctrine:migrations:migrate`
4. Добавляем класс `App\Infrastructure\Storage\LocalFileStorage`
    ```php
    <?php
    
    namespace App\Infrastructure\Storage;
    
    use Symfony\Component\HttpFoundation\File\File;
    use Symfony\Component\HttpFoundation\File\UploadedFile;
    
    class LocalFileStorage
    {
        public function storeUploadedFile(UploadedFile $uploadedFile): File
        {
            $fileName = sprintf('%s.%s', uniqid('image', true), $uploadedFile->getClientOriginalExtension());
                
            return $uploadedFile->move('upload', $fileName);
        }
    }
    ```
5. Добавляем класс `App\Domain\Service\FileService`
    ```php
    <?php
    
    namespace App\Domain\Service;
    
    use App\Infrastructure\Storage\LocalFileStorage;
    use Symfony\Component\HttpFoundation\File\File;
    use Symfony\Component\HttpFoundation\File\UploadedFile;
    
    class FileService
    {
        public function __construct(private readonly LocalFileStorage $localFileStorage)
        {
        }
    
        public function storeUploadedFile(UploadedFile $uploadedFile): File
        {
            return $this->localFileStorage->storeUploadedFile($uploadedFile);
        }
    }
    ```
6. В классе `App\Infrastructure\Repository\UserRepository` добавляем метод `updateAvatarLink`
    ```php
    public function updateAvatarLink(User $user, string $avatarLink): void
    {
        $user->setAvatarLink($avatarLink);
        $this->flush();
    }
    ```
7. В классе `App\Domain\Service\UserService` добавляем метод `updateAvatarLink`
    ```php 
    public function updateAvatarLink(User $user, string $avatarLink): void
    {
        $this->userRepository->updateAvatarLink($user, $avatarLink);
    }
    ```
8. Добавляем класс `App\Controller\Web\UpdateUserAvatarLink\v1\Manager`
    ```php
    <?php
    
    namespace App\Controller\Web\UpdateUserAvatarLink\v1;
    
    use App\Domain\Entity\User;
    use App\Domain\Service\FileService;
    use App\Domain\Service\UserService;
    use Symfony\Component\HttpFoundation\File\UploadedFile;
    
    class Manager
    {
        public function __construct(
            private readonly FileService $fileService,
            private readonly UserService $userService,
        ) {
        }
    
        public function updateUserAvatarLink(User $user, UploadedFile $file): void
        {
            $this->fileService->storeUploadedFile($file);
            $this->userService->updateAvatarLink($user, $file->getRealPath());
        }
    }
    ```
9. Добавляем класс `App\Controller\Web\UpdateUserAvatarLink\v1\Controller`
    ```php
    <?php
    
    namespace App\Controller\Web\UpdateUserAvatarLink\v1;
    
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
        public function __construct(private readonly Manager $manager)
        {
        }
    
        #[Route(path: '/api/v1/update-user-avatar-link/{id}', methods: ['POST'])]
        public function getUserByLoginAction(#[MapEntity(id: 'id')] User $user, Request $request): Response
        {
            $this->manager->updateUserAvatarLink($user, $request->files->get('image'));
            
            return new JsonResponse(['user' => $user->toArray()], Response::HTTP_OK);
        }
    }
    ```
10. Выполняем запрос Update user avatar link из Postman-коллекции v2, видим исходный путь к файлу в каталоге tmp
11. Заходим в контейнер `php` командой `docker exec -it php sh`. Дальнейшие команды выполняются из контейнера
12. Проверяем, что файл появился в каталоге `public/upload` с новым именем и исходным расширением

## Исправим получение пути

1. В классе `App\Controller\Web\v1\UpdateUserAvatarLink\Manager` исправим метод `uploadUserAvatarLink`
    ```php
    public function updateUserAvatarLink(User $user, UploadedFile $uploadedFile): void
    {
        $file = $this->fileService->storeUploadedFile($uploadedFile);
        $this->userService->updateAvatarLink($user, $file->getRealPath());
    }
    ```
2. Ещё раз выполняем запрос Upload file из Postman-коллекции v2, видим корректный путь к файлу

## Сделаем ссылку открываемой

1. Исправляем класс `App\Controller\Web\UpdateUserAvatarLink\v1\Manager`
    ```php
    <?php
    
    namespace App\Controller\Web\UpdateUserAvatarLink\v1;
    
    use App\Domain\Entity\User;
    use App\Domain\Service\FileService;
    use App\Domain\Service\UserService;
    use Symfony\Component\HttpFoundation\File\UploadedFile;
    
    class Manager
    {
        public function __construct(
            private readonly FileService $fileService,
            private readonly UserService $userService,
            private readonly string $baseUrl,
            private readonly string $uploadPrefix,
        ) {
        }
    
        public function updateUserAvatarLink(User $user, UploadedFile $uploadedFile): void
        {
            $file = $this->fileService->storeUploadedFile($uploadedFile);
            $path = $this->baseUrl . str_replace($this->uploadPrefix, '', $file->getRealPath());
            $this->userService->updateAvatarLink($user, $path);
        }
    }
     ```
2. В файле `config/services.yaml`
   1. добавляем в секцию `parameters` новые параметры
       ```yaml
       parameters:
         baseUrl: 'http://localhost:7777'
         uploadPrefix: '/app/public'
       ```
   2. добавляем описание сервиса `App\Controller\Web\UpdateUserAvatarLink\v1\Manager`
       ```yaml
       App\Controller\Web\UpdateUserAvatarLink\v1\Manager:
           arguments:
               $baseUrl: '%baseUrl%'
               $uploadPrefix: '%uploadPrefix%'
       ```
3. Ещё раз выполняем запрос Upload file из Postman-коллекции v2, открываем ссылку, видим картинку

## Добавляем ExceptionListener

1. Добавляем интерфейс `App\Controller\Exception\HttpCompliantExceptionInterface`
    ```php
    <?php
    
    namespace App\Controller\Exception;
    
    interface HttpCompliantExceptionInterface
    {
        public function getHttpCode(): int;
        
        public function getHttpResponseBody(): string;
    }
    ```
2. Добавляем класс `App\Controller\Exception\DeprecatedException`
    ```php
    <?php
    
    namespace App\Controller\Exception;
    
    use Exception;
    use Symfony\Component\HttpFoundation\Response;
    
    class DeprecatedException extends Exception implements HttpCompliantExceptionInterface
    {
        public function getHttpCode(): int
        {
            return Response::HTTP_GONE;
        }
    
        public function getHttpResponseBody(): string
        {
            return 'This API method is deprecated';
        }
    }
    ```
3. В классе `App\Controller\Web\UpdateUserAvatarLink\v1\Manager` исправляем метод `updateUserAvatarLink`
    ```php
    public function updateUserAvatarLink(User $user, UploadedFile $uploadedFile): void
    {
        throw new DeprecatedException();
        $file = $this->fileService->storeUploadedFile($uploadedFile);
        $path = $this->baseUrl . str_replace($this->uploadPrefix, '', $file->getRealPath());
        $this->userService->updateAvatarLink($user, $path);
    }
    ```
4. Добавляем класс `App\Application\EventListener\KernelExceptionEventListener`
    ```php
    <?php
    
    namespace App\Application\EventListener;
    
    use App\Controller\Exception\HttpCompliantExceptionInterface;
    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\HttpKernel\Event\ExceptionEvent;
    
    class KernelExceptionEventListener
    {
        public function onKernelException(ExceptionEvent $event): void
        {
            $exception = $event->getThrowable();
    
            if ($exception instanceof HttpCompliantExceptionInterface) {
                $event->setResponse($this->getHttpResponse($exception->getHttpResponseBody(), $exception->getHttpCode()));
            }
        }
    
        private function getHttpResponse($message, $code): Response {
            return new JsonResponse(['message' => $message], $code);
        }
    }
    ```
5. В файл `config/services.yaml` добавляем описание нового сервиса
    ```yaml
    App\Application\EventListener\KernelExceptionEventListener:
        tags:
            - { name: kernel.event_listener, event: kernel.exception }
6. Выполняем запрос Update user avatar link из Postman-коллекции v2, видим код ответа 410 и наше сообщение об ошибке

## Добавляем работу с EventDispatcher

1. Добавляем класс `App\Domain\Event\CreateUserEvent`
    ```php
    <?php
    
    namespace App\Domain\Event;
    
    class CreateUserEvent
    {
        public ?int $id = null;
   
        public function __construct(
            public readonly string $login,
            public readonly ?string $phone = null,
            public readonly ?string $email = null,
        ) {
        }
    }
    ```
2. Добавляем класс `App\Domain\EventSubscriber\UserEventSubscriber`
    ```php
    <?php
    
    namespace App\Domain\EventSubscriber;
    
    use App\Domain\Event\CreateUserEvent;
    use App\Domain\Service\UserService;
    use Symfony\Component\EventDispatcher\EventSubscriberInterface;
    
    class UserEventSubscriber implements EventSubscriberInterface
    {
        public function __construct(private readonly UserService $userService)
        {
        }
    
        public static function getSubscribedEvents(): array
        {
            return [
                CreateUserEvent::class => 'onCreateUser'
            ];
        }
    
        public function onCreateUser(CreateUserEvent $event): void
        {
            $user = null;
            
            if ($event->phone !== null) {
                $user = $this->userService->createWithPhone($event->login, $event->phone);
            } elseif ($event->email !== null) {
                $user = $this->userService->createWithEmail($event->login, $event->email);
            }
            
            $event->id = $user?->getId();
        }
    }
    ```
3. Исправляем класс `App\Controller\Web\CreateUser\v1\Manager`
    ```
    <?php
    
    namespace App\Controller\Web\CreateUser\v1;
    
    use App\Domain\Entity\User;
    use App\Domain\Event\CreateUserEvent;
    use App\Domain\Service\UserService;
    use Symfony\Component\EventDispatcher\EventDispatcherInterface;
    
    class Manager
    {
        public function __construct(
            private readonly UserService $userService,
            private readonly EventDispatcherInterface $eventDispatcher,
        ) {
        }
    
        public function create(string $login, ?string $phone = null, ?string $email = null): ?User
        {
            $event = new CreateUserEvent($login, $phone, $email);
            $this->eventDispatcher->dispatch($event);
    
            return $event->id === null ? null : $this->userService->findUserById($event->id);
        }
    } 
    ```
4. Выполняем запрос Add user из Postman-коллекции v2, видим успешный ответ
