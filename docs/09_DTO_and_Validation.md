# Компонент HttpFoundation

Запускаем контейнеры командой `docker-compose up -d`

## Добавляем входящий DTO

1. Заходим в контейнер командой `docker exec -it php sh`, дальнейшие команды выполняются из контейнера.
2. Устанавливаем пакеты `symfony/serializer-pack` и `symfony/validator`
3. Добавляем класс `App\Controller\Web\CreateUser\v1\Input`
    ```php
    <?php
    
    namespace App\Controller\Web\CreateUser\v1\Input;
    
    use Symfony\Component\Validator\Constraints as Assert;
    
    class CreateUserDTO
    {
        public function __construct(
            #[Assert\NotBlank]
            public readonly string $login,
            public readonly ?string $email,
            public readonly ?string $phone,
        ) {    
        }
    }
    ```
4. В классе `App\Controller\Web\CreateUser\v1\Manager` исправляем метод `create`
    ```php
    public function create(CreateUserDTO $createUserDTO): ?User
    {
        $event = new CreateUserEvent($createUserDTO->login, $createUserDTO->phone, $createUserDTO->email);
        $event = $this->eventDispatcher->dispatch($event);

        return $event->id === null ? null : $this->userService->findUserById($event->id);
    }
    ```
5. В классе `App\Controller\Web\CreateUser\v1\Controller` исправляем метод `__invoke`
    ```php
    #[Route(path: 'api/v1/user', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] CreateUserDTO $createUserDTO): Response
    {
        $user = $this->manager->create($createUserDTO);
        if ($user === null) {
            return new JsonResponse(null, Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($user->toArray());
    }
    ```
6. Выполняем запрос Add user из Postman-коллекции v2 с заполненными данными, видим успешный ответ
7. Выполняем запрос Add user из Postman-коллекции v2 без логина, видим ошибку 422

## Добавляем обработку ошибки

1. Исправляем класс `App\Application\EventListener\KernelExceptionEventListener`
    ```php
    <?php
    
    namespace App\Application\EventListener;
    
    use App\Controller\Exception\HttpCompliantExceptionInterface;
    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\HttpKernel\Event\ExceptionEvent;
    use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
    use Symfony\Component\Validator\ConstraintViolation;
    use Symfony\Component\Validator\Exception\ValidationFailedException;
    
    class KernelExceptionEventListener
    {
        public function onKernelException(ExceptionEvent $event): void
        {
            $exception = $event->getThrowable();
    
            if ($exception instanceof HttpCompliantExceptionInterface) {
                $event->setResponse($this->getHttpResponse($exception->getHttpResponseBody(), $exception->getHttpCode()));
            } elseif ($exception instanceof HttpExceptionInterface && $exception->getPrevious() instanceof ValidationFailedException) {
                $event->setResponse($this->getValidationFailedResponse($exception->getPrevious()));
            }
        }
    
        private function getHttpResponse($message, $code): Response {
            return new JsonResponse(['message' => $message], $code);
        }
        
        private function getValidationFailedResponse(ValidationFailedException $exception): Response {
            $response = [];
            foreach ($exception->getViolations() as $violation) {
                $response[$violation->getPropertyPath()] = $violation->getMessage();
            }
            return new JsonResponse($response, Response::HTTP_BAD_REQUEST);
        }
    }    
    ```
2. Выполняем запрос Add user из Postman-коллекции v2 без логина, видим JSON-ответ и ошибку 400

## Добавляем условную валидацию

1. К классу `App\Controller\Web\CreateUser\v1\Input\CreateUserDTO` добавляем атрибут
    ```php
    #[Assert\Expression(
        expression: '(this.email === null and this.phone !== null) or (this.phone === null and this.email !== null)',
        message: 'Either email or phone should be provided'
    )]
    ```
2. Выполняем запрос Add user из Postman-коллекции v2 только с телефоном, видим успешный ответ
3. Выполняем запрос Add user из Postman-коллекции v2 без е-мейла и телефона, видим JSON-ответ и ошибку 400, но не видим
   название свойства
4. Исправляем класс `App\Application\EventListener\KernelExceptionEventListener`
    ```php
    <?php
    
    namespace App\Application\EventListener;
    
    use App\Controller\Exception\HttpCompliantExceptionInterface;
    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\HttpKernel\Event\ExceptionEvent;
    use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
    use Symfony\Component\Validator\Exception\ValidationFailedException;
    
    class KernelExceptionEventListener
    {
        private const DEFAULT_PROPERTY = 'error';
        
        public function onKernelException(ExceptionEvent $event): void
        {
            $exception = $event->getThrowable();
    
            if ($exception instanceof HttpCompliantExceptionInterface) {
                $event->setResponse($this->getHttpResponse($exception->getHttpResponseBody(), $exception->getHttpCode()));
            } elseif ($exception instanceof HttpExceptionInterface && $exception->getPrevious() instanceof ValidationFailedException) {
                $event->setResponse($this->getValidationFailedResponse($exception->getPrevious()));
            }
        }
    
        private function getHttpResponse($message, $code): Response {
            return new JsonResponse(['message' => $message], $code);
        }
    
        private function getValidationFailedResponse(ValidationFailedException $exception): Response {
            $response = [];
            foreach ($exception->getViolations() as $violation) {
                $property = empty($violation->getPropertyPath()) ? self::DEFAULT_PROPERTY : $violation->getPropertyPath();
                $response[$property] = $violation->getMessage();
            }
            return new JsonResponse($response, Response::HTTP_BAD_REQUEST);
        }
    }
    ```
5. Выполняем запрос Add user из Postman-коллекции v2 с е-мейлом и телефоном, видим JSON-ответ и ошибку 400 с общим полем
   error

## Добавляем Model на слое домена

1. Добавляем класс `App\Domain\Model\CreateUserModel`
    ```php
    <?php
    
    namespace App\Domain\Model;
    
    use App\Domain\ValueObject\CommunicationChannelEnum;
    
    class CreateUserModel
    {
        public function __construct(
            public readonly string $login,
            public readonly string $communicationMethod,
            public readonly CommunicationChannelEnum $communicationChannel,
        ) {
        }
    }    
    ```
2. В классе `App\Domain\Entity\EmailUser` исправляем метод `setEmail`
    ```php
    public function setEmail(string $email): self
    {
        $this->email = $email;
        
        return $this;
    }
    ```
3. В классе `App\Domain\Entity\PhoneUser` исправляем метод `setPhone`
    ```php
    public function setPhone(string $phone): self
    {
        $this->phone = $phone;
        
        return $this;
    }
    ```
4. В классе `App\Domain\Service\UserService` добавляем метод `create`
    ```php
    public function create(CreateUserModel $createUserModel): User
    {
        $user = match($createUserModel->communicationChannel) {
            CommunicationChannelEnum::Email => (new EmailUser())->setEmail($createUserModel->communicationMethod),
            CommunicationChannelEnum::Phone => (new PhoneUser())->setPhone($createUserModel->communicationMethod),
        };
        $user->setLogin($createUserModel->login);
        $this->userRepository->create($user);

        return $user;
    }
    ```
5. Исправляем класс `App\Controller\Web\CreateUser\v1\Manager`
    ```php
    <?php
    
    namespace App\Controller\Web\CreateUser\v1;
    
    use App\Controller\Web\CreateUser\v1\Input\CreateUserDTO;
    use App\Domain\Entity\User;
    use App\Domain\Model\CreateUserModel;
    use App\Domain\Service\UserService;
    use App\Domain\ValueObject\CommunicationChannelEnum;
    
    class Manager
    {
        public function __construct(
            private readonly UserService $userService,
        ) {
        }
    
        public function create(CreateUserDTO $createUserDTO): User
        {
            $communicationMethod = $createUserDTO->phone ?? $createUserDTO->email;
            $communicationChannel = $createUserDTO->phone === null ? CommunicationChannelEnum::Email : CommunicationChannelEnum::Phone;
            $createUserModel = new CreateUserModel($createUserDTO->login, $communicationMethod, $communicationChannel);
    
            return $this->userService->create($createUserModel);
        }
    }    
    ```
6. В классе `App\Controller\Web\CreateUser\v1\Controller` исправляем метод `__invoke`
    ```php
    #[Route(path: 'api/v1/user', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] CreateUserDTO $createUserDTO): Response
    {
        $user = $this->manager->create($createUserDTO);

        return new JsonResponse($user->toArray());
    }
    ```
7. Выполняем запрос Add user из Postman-коллекции v2 с корректными данными, видим успешный ответ

## Добавляем ограничения в БД и валидацию на них

1. В классе `App\Domain\Entity\PhoneUser` исправляем поле `phone`
    ```php
    #[ORM\Column(type: 'string', length: 20, nullable: false)]
    private string $phone;
    ```
2. Выполняем команду `php bin/console doctrine:migrations:diff`
3. Проверяем сгенерированную миграцию и применяем её с помощью команды `php bin/console doctrine:migrations:migrate`
4. В классе `App\Controller\Web\CreateUser\v1\Input\CreateUserDTO` исправляем конструктор
    ```php
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $login,
        public readonly ?string $email,
        #[Assert\Length(max: 20)]
        public readonly ?string $phone,
    ) {
    }
    ```
5. Выполняем запрос Add user из Postman-коллекции v2 с длинным телефоном, видим ошибку с кодом 400

## Добавляем валидацию и фабрику для моделей

1. Исправляем класс `App\Domain\Model\CreateUserModel`
    ```php
    <?php
    
    namespace App\Domain\Model;
    
    use App\Domain\ValueObject\CommunicationChannelEnum;
    
    use Symfony\Component\Validator\Constraints as Assert;
    
    class CreateUserModel
    {
        public function __construct(
            #[Assert\NotBlank]
            public readonly string $login,
            #[Assert\NotBlank]
            #[Assert\When(
                expression: "this.communicationChannel.value === 'phone'",
                constraints: [new Assert\Length(max: 20)]
            )]
            public readonly string $communicationMethod,
            public readonly CommunicationChannelEnum $communicationChannel,
        ) {
        }
    }
    ```
2. Добавляем класс `App\Domain\Service\ModelFactory`
    ```php
    <?php
    
    namespace App\Domain\Service;
    
    use Symfony\Component\Validator\Exception\ValidationFailedException;
    use Symfony\Component\Validator\Validator\ValidatorInterface;
    
    /**
     * @template T
     */    
    class ModelFactory
    {
        public function __construct(
            private readonly ValidatorInterface $validator
        ) {
        }
        
        /**
         * @param class-string $modelClass
         * @return T
         */
        public function makeModel(string $modelClass, ...$parameters)
        {
            $model = new $modelClass(...$parameters);
            $violations = $this->validator->validate($model);
            if ($violations->count() > 0) {
                throw new ValidationFailedException($parameters, $violations);
            }
            
            return $model;
        }
    }    
    ```
3. Исправляем класс `App\Controller\Web\CreateUser\v1\Manager`
    ```php
    <?php
    
    namespace App\Controller\Web\CreateUser\v1;
    
    use App\Controller\Web\CreateUser\v1\Input\CreateUserDTO;
    use App\Domain\Entity\User;
    use App\Domain\Model\CreateUserModel;
    use App\Domain\Service\ModelFactory;
    use App\Domain\Service\UserService;
    use App\Domain\ValueObject\CommunicationChannelEnum;
    
    class Manager
    {
        public function __construct(
            /** @var ModelFactory<CreateUserModel> */
            private readonly ModelFactory $modelFactory,
            private readonly UserService $userService,
        ) {
        }
    
        public function create(CreateUserDTO $createUserDTO): User
        {
            $communicationMethod = $createUserDTO->phone ?? $createUserDTO->email;
            $communicationChannel = $createUserDTO->phone === null ? CommunicationChannelEnum::Email : CommunicationChannelEnum::Phone;
            $createUserModel = $this->modelFactory->makeModel(CreateUserModel::class, $createUserDTO->login, $communicationMethod, $communicationChannel);
    
            return $this->userService->create($createUserModel);
        }
    }
    ```
4. В классе `App\Controller\Web\CreateUser\v1\Input\CreateUserDTO` убираем условие на поле `$phone`
5. Выполняем запрос Add user из Postman-коллекции v2 с длинным телефоном, видим ошибку с кодом 500

## Исправляем обработку исключений

1. В классе `\App\Application\EventListener\KernelExceptionEventListener` исправляем метод `onKernelException`
    ```php
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof HttpCompliantExceptionInterface) {
            $event->setResponse($this->getHttpResponse($exception->getHttpResponseBody(), $exception->getHttpCode()));
        } else {
            if ($exception instanceof HttpExceptionInterface) {
                $exception = $exception->getPrevious();
            }
            if ($exception instanceof ValidationFailedException) {
                $event->setResponse($this->getValidationFailedResponse($exception));
            }
        }
    }
    ```
2. Выполняем запрос Add user из Postman-коллекции v2 с длинным телефоном, видим ошибку на поле из модели с кодом 400
3. В классе `App\Controller\Web\CreateUser\v1\Input\CreateUserDTO` возвращаем обратно условие на поле `$phone`

## Добавляем исходящий DTO

1. Добавляем класс `App\controller\Web\CreateUser\v1\Output\CreatedUserDTO`
   ```php
   <?php
   
   namespace App\Controller\Web\CreateUser\v1\Output;
   
   class CreatedUserDTO
   {
       public function __construct(
           public readonly int $id,
           public readonly string $login,
           public readonly ?string $avatarLink,
           public readonly string $createdAt,
           public readonly string $updatedAt,
           public readonly ?string $phone,
           public readonly ?string $email,
       ) {
       }
   }
   ```
2. Исправляем класс `App\Controller\Web\CreateUser\v1\Manager`
    ```php
    <?php
    
    namespace App\Controller\Web\CreateUser\v1;
    
    use App\Controller\Web\CreateUser\v1\Input\CreateUserDTO;
    use App\Controller\Web\CreateUser\v1\Output\CreatedUserDTO;
    use App\Domain\Entity\EmailUser;
    use App\Domain\Entity\PhoneUser;
    use App\Domain\Entity\User;
    use App\Domain\Model\CreateUserModel;
    use App\Domain\Service\ModelFactory;
    use App\Domain\Service\UserService;
    use App\Domain\ValueObject\CommunicationChannelEnum;
    
    class Manager
    {
        public function __construct(
            /** @var ModelFactory<CreateUserModel> */
            private readonly ModelFactory $modelFactory,
            private readonly UserService $userService,
        ) {
        }
    
        public function create(CreateUserDTO $createUserDTO): CreatedUserDTO
        {
            $communicationMethod = $createUserDTO->phone ?? $createUserDTO->email;
            $communicationChannel = $createUserDTO->phone === null ? CommunicationChannelEnum::Email : CommunicationChannelEnum::Phone;
            $createUserModel = $this->modelFactory->makeModel(CreateUserModel::class, $createUserDTO->login, $communicationMethod, $communicationChannel);
            $user = $this->userService->create($createUserModel);
    
            return new CreatedUserDTO(
                $user->getId(),
                $user->getLogin(),
                $user->getAvatarLink(),
                $user->getCreatedAt()->format('Y-m-d H:i:s'),
                $user->getUpdatedAt()->format('Y-m-d H:i:s'),
                $user instanceof PhoneUser ? $user->getPhone() : null,
                $user instanceof EmailUser ? $user->getEmail() : null,
            );
        }
    }
    ```
3. Исправляем класс `App\Controller\Web\CreateUser\v1\Controller`
    ```php
    <?php
    
    namespace App\Controller\Web\CreateUser\v1;
    
    use App\Controller\Web\CreateUser\v1\Input\CreateUserDTO;
    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\HttpKernel\Attribute\AsController;
    use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
    use Symfony\Component\Routing\Attribute\Route;
    use Symfony\Component\Serializer\Encoder\JsonEncoder;
    use Symfony\Component\Serializer\SerializerInterface;
    
    #[AsController]
    class Controller
    {
        public function __construct(
            private readonly Manager $manager,
            private readonly SerializerInterface $serializer
        ) {
        }
    
        #[Route(path: 'api/v1/user', methods: ['POST'])]
        public function __invoke(#[MapRequestPayload] CreateUserDTO $createUserDTO): Response
        {
            $user = $this->manager->create($createUserDTO);
    
            return new JsonResponse($this->serializer->serialize($user, JsonEncoder::FORMAT), Response::HTTP_OK, [], true);
        }
    }    
    ```

## Выносим сериализацию из контроллера

1. Добавляем маркерный интерфейс `App\Controller\DTO\OutputDTOInterface`
    ```php
    <?php
    
    namespace App\Controller\DTO;
    
    interface OutputDTOInterface
    {
    }    
    ```
2. Имплементируем добавленный интерфейс в классе `App\Controller\Web\CreateUser\v1\Output\CreatedUserDTO`
3. Добавляем класс `App\Application\EventListener\KernelViewEventListener`
    ```php
    <?php
    
    namespace App\Application\EventListener;
    
    use App\Controller\DTO\OutputDTOInterface;
    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\HttpKernel\Event\ViewEvent;
    use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
    use Symfony\Component\Serializer\SerializerInterface;
    
    class KernelViewEventListener
    {
        public function __construct(private readonly SerializerInterface $serializer)
        {
        }
    
        public function onKernelView(ViewEvent $event): void
        {
            $dto = $event->getControllerResult();
    
            if ($dto instanceof OutputDTOInterface) {
                $event->setResponse($this->getDTOResponse($dto));
            }
        }
    
        private function getDTOResponse($data): Response {
            $serializedData = $this->serializer->serialize($data, 'json', [AbstractObjectNormalizer::SKIP_NULL_VALUES => true]);
    
            return new JsonResponse($serializedData, Response::HTTP_OK, [], true);
        }
    }    
    ```
4. В файле `config/services.yaml` добавляем описание нового сервиса
    ```yaml
    App\Application\EventListener\KernelViewEventListener:
        tags:
            - { name: kernel.event_listener, event: kernel.view }
    ```
5. Исправляем класс `App\Controller\Web\CreateUser\v1\Controller`
    ```php
    <?php
    
    namespace App\Controller\Web\CreateUser\v1;
    
    use App\Controller\Web\CreateUser\v1\Input\CreateUserDTO;
    use App\Controller\Web\CreateUser\v1\Output\CreatedUserDTO;
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
    
        #[Route(path: 'api/v1/user', methods: ['POST'])]
        public function __invoke(#[MapRequestPayload] CreateUserDTO $createUserDTO): CreatedUserDTO
        {
            return $this->manager->create($createUserDTO);
        }
    } 
    ```
6. Выполняем запрос Add user из Postman-коллекции v2 с корректными данными, видим успешный ответ
