# Авторизация и аутентификация

Запускаем контейнеры командой `docker-compose up -d`

## Добавляем пререквизиты

1. Заходим в контейнер `php` командой `docker exec -it php sh`. Дальнейшие команды выполняются из контейнера
2. Устанавливаем пакет `symfony/security-bundle`
3. Устанавливаем в dev-режиме пакет `symfony/maker-bundle`
4. В файле `config/packages/security.yaml`
   1. Исправляем секцию `providers`
       ```yaml
       providers:
           app_user_provider:
               entity:
                   class: App\Domain\Entity\User
                   property: login
       ```
   2. В секции `firewalls.main` заменяем `provider: users_in_memory` на `provider: app_user_provider`
5. Добавляем перечисление `App\Domain\ValueObject\RoleEnum`
    ```php
    <?php
    
    namespace App\Domain\ValueObject;
    
    enum RoleEnum: string
    {
        case ROLE_USER = 'ROLE_USER';
    } 
    ```
6. В классе `App\Domain\Entity\User`
   1. добавляем поле `$roles`, а также геттер и сеттер для него
       ```php
       #[ORM\Column(type: 'json', length: 1024, nullable: false)]
       private array $roles = [];

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
       ```
   2. имплементируем `Symfony\Component\Security\Core\User\UserInterface`
       ```php
       public function eraseCredentials(): void
       {
       }
    
       public function getUserIdentifier(): string
       {
           return $this->login;
       }
       ``` 
   3. имплементируем `Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface` (нужный метод уже есть)
7. Генерируем миграцию командой `php bin/console doctrine:migrations:diff`
8. Проверяем сгенерированную миграцию и применяем её с помощью команды `php bin/console doctrine:migrations:migrate`
9. Исправляем класс `App\Domain\Model\CreateUserModel`
    ```php
    <?php
    
    namespace App\Domain\Model;
    
    use App\Domain\ValueObject\CommunicationChannelEnum;
    
    use Symfony\Component\Validator\Constraints as Assert;
    
    class CreateUserModel
    {
        /**
         * @param string[] $roles
         */
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
            public readonly string $password = 'myPass',
            public readonly int $age = 18,
            public readonly bool $isActive = true,
            public readonly array $roles = [],
        ) {
        }
    } 
    ```
10. В классе `App\Domain\Service\UserService`
    1. добавляем инъекцию `UserPasswordEncoderInterface`
        ```php
        public function __construct(
            private readonly UserRepository $userRepository,
            private readonly UserPasswordHasherInterface $userPasswordHasher,
        ) {
        }
        ```
    2. исправляем метод `create`
        ```php
        public function create(CreateUserModel $createUserModel): User
        {
            $user = match($createUserModel->communicationChannel) {
                CommunicationChannelEnum::Email => (new EmailUser())->setEmail($createUserModel->communicationMethod),
                CommunicationChannelEnum::Phone => (new PhoneUser())->setPhone($createUserModel->communicationMethod),
            };
            $user->setLogin($createUserModel->login);
            $user->setPassword($this->userPasswordHasher->hashPassword($user, $createUserModel->password));
            $user->setAge($createUserModel->age);
            $user->setIsActive($createUserModel->isActive);
            $user->setRoles($createUserModel->roles);
            $this->userRepository->create($user);
    
            return $user;
        }
        ```
11. Добавляяем класс `App\Controller\Web\CreateUser\v2\Input\CreateUserDTO`
    ```php
    <?php
    
    namespace App\Controller\Web\CreateUser\v2\Input;
    
    use Symfony\Component\Validator\Constraints as Assert;
    
    #[Assert\Expression(
        expression: '(this.email === null and this.phone !== null) or (this.phone === null and this.email !== null)',
        message: 'Eiteher email or phone should be provided',
    )]
    class CreateUserDTO
    {
        public function __construct(
            #[Assert\NotBlank]
            public readonly string $login,
            public readonly ?string $email,
            #[Assert\Length(max: 20)]
            public readonly ?string $phone,
            #[Assert\NotBlank]
            public readonly string $password,
            #[Assert\NotBlank]
            public readonly int $age,
            #[Assert\NotNull]
            public readonly bool $isActive,
            /** @var string[] $roles */
            public readonly array $roles,
        ) {
        }
    }
    ```
12. Добавляем класс `App\Controller\Web\CreateUser\v2\Output\CreatedUserDTO`
    ```php
    <?php
    
    namespace App\Controller\Web\CreateUser\v2\Output;
    
    use App\Controller\DTO\OutputDTOInterface;
    
    class CreatedUserDTO implements OutputDTOInterface
    {
        public function __construct(
            public readonly int $id,
            public readonly string $login,
            public readonly ?string $avatarLink,
            /** @var string[] $roles */
            public readonly array $roles,
            public readonly string $createdAt,
            public readonly string $updatedAt,
            public readonly ?string $phone,
            public readonly ?string $email,
        ) {
        }
    }
    ```
13. Добавляем класс `App\Controller\Web\CreateUser\v2\Manager`
    ```php
    <?php
    
    namespace App\Controller\Web\CreateUser\v2;
    
    use App\Controller\Web\CreateUser\v2\Input\CreateUserDTO;
    use App\Controller\Web\CreateUser\v2\Output\CreatedUserDTO;
    use App\Domain\Entity\EmailUser;
    use App\Domain\Entity\PhoneUser;
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
14. Добавляем класс `App\Controller\Web\CreateUser\v2\Controller`
    ```php
    <?php
    
    namespace App\Controller\Web\CreateUser\v2;
    
    use App\Controller\Web\CreateUser\v2\Input\CreateUserDTO;
    use App\Controller\Web\CreateUser\v2\Output\CreatedUserDTO;
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
    
        #[Route(path: 'api/v2/user', methods: ['POST'])]
        public function __invoke(#[MapRequestPayload] CreateUserDTO $createUserDTO): CreatedUserDTO
        {
            return $this->manager->create($createUserDTO);
        }
    }
    ```
15. Выполняем запрос Add user v2 из Postman-коллекции v3, видим, что пользователь добавлен в БД и пароль захэширован

## Добавляем форму логина

1. В файле `config/packages/security.yaml` в секции `firewall.main` добавляем `security:false`
2. Генерируем форму логина командой `php bin/console make:security:form-login`, не создавая Logout URL и тесты
3. Переименовываем класс `App\Controller\SecurityController` в `App\Controller\Web\Login\v1\Controller`
4. В файле `src/templates/security/login.html.twig` исправляем наследование на базовый шаблон `layout.twig`
5. Переходим по адресу `http://localhost:7777/login` и вводим логин/пароль пользователя, которого создали при проверке
   API. Видим, что после нажатия на `Sign in` ничего не происходит.

## Включаем security

1. Убираем в файле `config/packages/security.yaml` в секции `firewall.main` строку `security:false`
2. Ещё раз переходим по адресу `http://localhost:7777/login` и вводим логин/пароль пользователя, после нажатия на
   `Sign in` получаем перенаправление на корневую страницу с установленной сессией в куки

## Добавляем перенаправление

1. В файле `config/packages/security.yaml` исправляем секцию `security.firewalls.main.form_login:
    ```yaml
    form_login:
        login_path: app_login
        check_path: app_login
        enable_csrf: true
        default_target_path: app_web_renderuserlist_v1__invoke
    ```
2. Проверяем, что всё заработало

## Добавляем авторизацию для ROLE_ADMIN

1. В файле `config/packages/security.yaml` в секцию `access_control` добавляем условие
     ```yaml
     - { path: ^/api/v1/user, roles: ROLE_ADMIN, methods: [DELETE] }
     ```
2. Выполняем запрос Add user из Postman-коллекции v3 с другим значением логина, запоминаем id добавленного пользователя
3. Выполняем запрос Delete user by id из Postman-коллекции v3 с userId добавленного пользователя, добавив Cookie
   `PHPSESSID`, которую можно посмотреть в браузере после успешного логина. Проверяем, что возвращается ответ 403 с
   сообщением `Access denied`
4. Добавляем роль `ROLE_ADMIN` пользователю в БД, перелогиниваемся, чтобы получить корректную сессию и проверяем, что
   стал возвращаться ответ 200 и пользователь удалён из БД

## Добавляем авторизацию для ROLE_VIEW

1. В файле `config/packages/security.yaml` в секции `access_control` добавляем условие
     ```yaml
     - { path: ^/api/v1/get-user-list, roles: ROLE_VIEW, methods: [GET] }
     ```
2. Перезагружаем страницу в браузере, видим, что возвращается ответ 403 с сообщением `Access denied`

## Добавляем иерархию ролей

1. В классе `App\Controller\Web\RenderUserList\v1\Manager` исправляем метод `getUserListData`
    ```php
    public function getUserListData(): array
    {
        $mapper = static function (User $user): array {
            $result = [
                'id' => $user->getId(),
                'login' => $user->getLogin(),
                'communicationChannel' => null,
                'communicationMethod' => null,
                'roles' => $user->getRoles(),
            ];
            if ($user instanceof PhoneUser) {
                $result['communicationChannel'] = CommunicationChannelEnum::Phone->value;
                $result['communicationMethod'] = $user->getPhone();
            }
            if ($user instanceof EmailUser) {
                $result['communicationChannel'] = CommunicationChannelEnum::Email->value;
                $result['communicationMethod'] = $user->getEmail();
            }

            return $result;
        };

        return array_map($mapper, $this->userService->findAll());
    }
    ```
2. Исправляем шаблон `templates/user-table.twig`
    ```html
    {% extends 'layout.twig' %}
       
    {% import 'macros.twig' as macros %}
    
    {% block title %}
        {{ my_greet('User') }}
    {% endblock %}
    {% block body %}
        <table class="table table-hover">
            <tbody>
            <tr><th>ID</th><th>Логин</th><th>Роли</th><th>Канал коммуникации</th><th>Адрес</th></tr>
            {{ macros.user_table_body(users) }}
            </tbody>
        </table>
    {% endblock %}
    ```
3. В шаблоне `templates/macros.twig` исправляем макрос `user_table_body`
    ```html
    {% macro user_table_body(users) %}
        {% for user in users %}
            <tr>
                <td>{{ user.id|my_twice }}</td>
                <td>{{ user.login }}</td>
                <td>
                    <ul>
                    {% for role in user.roles %}
                        <li>{{ role }}</li>
                    {% endfor %}
                    </ul>    
                </td>
                <td>{{ user.communicationChannel }}</td>
                <td>({{ user.communicationMethod }})</td>
            </tr>
        {% endfor %}
    {% endmacro %}    
    ```
4. Добавляем в файл `config/packages/security.yaml` секцию `security.role_hierarchy`
     ```yaml
     role_hierarchy:
         ROLE_ADMIN: ROLE_VIEW
     ```
5. Ещё раз перезагружаем страницу. Видим список с ролями и видим, что у пользователя нет роли `ROLE_VIEW`.

## Добавляем авторизацию через атрибут

1. В классе `App\Controller\Web\GetUser\v1\Controller` добавляем атрибут на метод `__invoke`
    ```php
    #[IsGranted('ROLE_GET_LIST')]
    ```
2. Выполняем запрос Get user из Postman-коллекции v3, видим ошибку 403

## Добавляем авторизацию через код

1. В классе `App\Controller\Web\RenderUserList\v1\Controller` исправляем метод `__invoke`
    ```php
    #[Route(path: '/api/v1/get-user-list', methods: ['GET'])]
    public function __invoke(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_GET_LIST');
        
        return $this->render('user-table.twig', ['users' => $this->userManager->getUserListData()]);
    }
    ```
2. Перезагружаем страницу, видим ошибку 403.
3. В файле `config/packages/security.yaml` исправляем секцию `security.role_hierarchy`
    ```yaml
    role_hierarchy:
        ROLE_ADMIN:
            - ROLE_VIEW
            - ROLE_GET_LIST
    ```
4. Перезагружаем страницу, видим таблицу с пользователями.
5. Выполняем запрос Get user из Postman-коллекции v3, видим успешный ответ.

## Добавляем Voter

1. Добавляем класс `App\Application\Security\Voter\UserVoter`
    ```php
    <?php
    
    namespace App\Application\Security\Voter;
    
    use App\Domain\Entity\User;
    use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
    use Symfony\Component\Security\Core\Authorization\Voter\Voter;
    
    class UserVoter extends Voter
    {
        public const DELETE = 'delete';
    
        protected function supports(string $attribute, $subject): bool
        {
            return $attribute === self::DELETE && ($subject instanceof User);
        }
    
        protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
        {
            $user = $token->getUser();
            if (!$user instanceof User) {
                return false;
            }
    
            /** @var User $subject */
            return $user->getId() !== $subject->getId();
        }
    }
    ```
2. Добавляем класс `App\Controller\Exception\AccessDeniedException`
    ```php
    <?php
    
    namespace App\Controller\Exception;
    
    use Exception;
    use Symfony\Component\HttpFoundation\Response;
    
    class AccessDeniedException extends Exception implements HttpCompliantExceptionInterface
    {
        public function getHttpCode(): int
        {
            return Response::HTTP_FORBIDDEN;
        }
    
        public function getHttpResponseBody(): string
        {
            return 'Access denied';
        }
    }    
    ```
3. Исправляем класс `App\Controller\Web\DeleteUser\v1\Manager`
    ```php
    <?php
    
    namespace App\Controller\Web\DeleteUser\v1;
    
    use App\Application\Security\Voter\UserVoter;
    use App\Controller\Exception\AccessDeniedException;
    use App\Domain\Entity\User;
    use App\Domain\Service\UserService;
    use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
    
    class Manager
    {
        public function __construct(
            private readonly UserService $userService,
            private readonly AuthorizationCheckerInterface $authorizationChecker,
        ) {
        }
    
        /**
         * @throws AccessDeniedException
         */
        public function deleteUser(User $user): void
        {
            if (!$this->authorizationChecker->isGranted(UserVoter::DELETE, $user)) {
                throw new AccessDeniedException();
            }
            $this->userService->remove($user);
        }
    }
    ```
4. Выполняем запрос Add user из Postman-коллекции v3 с новым значением логина, запоминаем id добавленного пользователя
5. Выполняем запрос Delete user by id из Postman-коллекции v3 сначала с идентификатором добавленного пользователя, потом
   с идентификатором залогиненного пользователя. Проверяем, что в первом случае ответ 200, во втором - 403

## Изменяем стратегию для Voter'ов

1. Выполняем запрос Add user v3 из Postman-коллекции v3 с новым значением логина, запоминаем id добавленного
   пользователя
2. В файл `config/packages/security.yaml` добавляем секцию `security.access_decision_manager`
     ```yaml
     access_decision_manager:
         strategy: consensus
     ```
3. Добавляем класс `App\Application\Security\Voter\FakeUserVoter`
     ```php
     <?php
    
     namespace App\Application\Security\Voter;
    
     use App\Domain\Entity\User;
     use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
     use Symfony\Component\Security\Core\Authorization\Voter\Voter;
    
     class FakeUserVoter extends Voter
     {
         protected function supports(string $attribute, $subject): bool
         {
             return $attribute === UserVoter::DELETE && ($subject instanceof User);
         }
    
         protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
         {
             return false;
         }
     }
     ```
4. Добавляем класс `App\Application\Security\Voter\DummyUserVoter`
     ```php
     <?php
    
     namespace App\Application\Security\Voter;
        
     use App\Domain\Entity\User;
     use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
     use Symfony\Component\Security\Core\Authorization\Voter\Voter;
    
     class DummyUserVoter extends Voter
     {
         protected function supports(string $attribute, $subject): bool
         {
             return $attribute === UserVoter::DELETE && ($subject instanceof User);
         }
    
         protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
         {
             return false;
         }
     }
     ```
5. Выполняем запрос Add user из Postman-коллекции v3 с новым значением логина, запоминаем id добавленного пользователя
6. Выполняем запрос Delete user by id из Postman-коллекции v3 с идентификатором добавленного пользователя, видим ошибку 
   403
7. Удаляем класс `App\Application\Security\Voter\DummyUserVoter`
8. Ещё раз выполняем запрос Delete user by id из Postman-коллекции v3 с идентификатором добавленного пользователя, видим
   успешный ответ
