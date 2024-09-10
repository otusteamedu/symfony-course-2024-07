# Twig и Symfony Forms

Запускаем контейнеры командой `docker-compose up -d`

## Выводим список пользователей с помощью Twig
 
1. Заходим в контейнер `php` командой `docker exec -it php sh`. Дальнейшие команды выполняются из контейнера
2. Устанавливаем пакет `symfony/twig-bundle`
3. Создаём файл `templates/user-list.twig`
    ```html
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>User list</title>
    </head>
    <body>
    <ul id="user.list">
        {% for user in users %}
            <li>{{ user.id }}. {{ user.login|lower }} {{ user.communicationChannel|upper }} ({{ user.communicationMethod }}) </li>
        {% endfor %}
    </ul>
    </body>
    </html>
    ```
4. Добавляем класс `App\Controller\Web\RenderUserList\v1\Manager`
    ```php
    <?php
    
    namespace App\Controller\Web\RenderUserList\v1;
    
    use App\Domain\Entity\EmailUser;
    use App\Domain\Entity\PhoneUser;
    use App\Domain\Entity\User;
    use App\Domain\Service\UserService;
    use App\Domain\ValueObject\CommunicationChannelEnum;
    
    class Manager
    {
        public function __construct(private readonly UserService $userService) {
        }
        
        public function getUserListData(): array
        {
            $mapper = static function (User $user): array {
                $result = [
                    'id' => $user->getId(),
                    'login' => $user->getLogin(),
                    'communicationChannel' => null,
                    'communicationMethod' => null,
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
    }    
    ```
5. Добавляем класс `App\Controller\Web\RenderUserList\v1\Controller`
    ```php
    <?php
    
    namespace App\Controller\Web\RenderUserList\v1;
    
    use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\Routing\Attribute\Route;
    
    class Controller extends AbstractController
    {
        public function __construct(private readonly Manager $userManager)
        {
        }
    
        #[Route(path: '/api/v1/get-user-list', methods: ['GET'])]
        public function __invoke(): Response
        {
            return $this->render('user-list.twig', ['users' => $this->userManager->getUserListData()]);
        }
    } 
    ```
6. Заходим по адресу `http://localhost:7777/api/v1/get-user-list`, видим список пользователей 

## Добавляем наследование в шаблоны

1. Переименовываем файл `templates/base.html.twig` в `layout.twig`
2. Исправляем файл `templates/user-list.twig`
    ```html
    {% extends 'layout.twig' %}

    {% block title %}
    User list
    {% endblock %}
    {% block body %}
    <ol id="user.list">
        {% for user in users %}
            <li>{{ user.id }}. {{ user.login|lower }} {{ user.communicationChannel|upper }} ({{ user.communicationMethod }}) </li>
        {% endfor %}
    </ol>
    {% endblock %}
    ```
3. Обновляем страницу в браузере, видим, что список стал нумерованным

## Добавляем повторяющиеся блоки

1. Исправляем файл `templates/layout.twig`
    ```html
    <!DOCTYPE html>
    <html>
        <head>
            <meta charset="UTF-8">
            <title>{% block title %}Welcome!{% endblock %}</title>
            {# Run `composer require symfony/webpack-encore-bundle`
               and uncomment the following Encore helpers to start using Symfony UX #}
            {% block stylesheets %}
                {#{{ encore_entry_link_tags('app') }}#}
            {% endblock %}
    
            {% block javascripts %}
                {#{{ encore_entry_script_tags('app') }}#}
            {% endblock %}
        </head>
        <body>
            {% block body %}{% endblock %}
            {% block footer %}{% endblock %}
        </body>
    </html>
    ```
2. Исправляем файл `templates/user-list.twig`
    ```html
    {% extends 'layout.twig' %}

    {% block title %}
    User list
    {% endblock %}
    {% block body %}
    <ol id="user.list">
         {% for user in users %}
            <li>{{ user.id }}. {{ user.login|lower }} {{ user.communicationChannel|upper }} ({{ user.communicationMethod }}) </li>
         {% endfor %}
    </ol>
    {% endblock %}
    {% block footer %}
    <h1>Footer</h1>
    {{ block('body') }}
    <h1>Repeat twice</h1>
    {{ block('body') }}
    {% endblock %}
    ```
3. Обновляем страницу в браузере, видим, что список выводится трижды с заголовками между списками

## Добавляем таблицу в шаблон

1. Создаём файл `templates/user-table.twig`
    ```html
    {% extends 'layout.twig' %}
    
    {% block title %}
        User table
    {% endblock %}
    {% block body %}
        <table>
            <tbody>
            <tr><th>ID</th><th>Логин</th><th>Канал коммуникации</th><th>Адрес</th></tr>
            {% for user in users %}
                <tr><td>{{ user.id }}</td><td>{{ user.login }}</td><td>{{ user.communicationChannel }}</td><td>({{ user.communicationMethod }})</td></tr>
            {% endfor %}
            </tbody>
        </table>
    {% endblock %}
    ```
2. Исправляем в классе `App\Controller\Web\RenderUserList\v1\Controller` метод `__invoke`
    ```php
    #[Route(path: '/api/v1/get-user-list', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('user-table.twig', ['users' => $this->userManager->getUserListData()]);
    }
    ```
3. Обновляем страницу в браузере, видим, что вместо списка выведена таблица

## Добавляем макрос

1. Создаём файл `templates/macros.twig`
    ```html
    {% macro user_table_body(users) %}
         {% for user in users %}
             <tr><td>{{ user.id }}</td><td>{{ user.login }}</td><td>{{ user.communicationChannel }}</td><td>({{ user.communicationMethod }})</td></tr>
         {% endfor %}
    {% endmacro %}
    ```
2. Исправляем файл `templates/user-table.twig`:
    ```html
    {% extends 'layout.twig' %}

    {% import 'macros.twig' as macros %}

    {% block title %}
    User table
    {% endblock %}
    {% block body %}
    <table>
         <tbody>
            <tr><th>ID</th><th>Логин</th><th>Канал коммуникации</th><th>Адрес</th></tr>
            {{ macros.user_table_body(users) }}
         </tbody>
    </table>
    {% endblock %}
    ```
3. Обновляем страницу в браузере, видим, что таблица всё ещё отображается

## Добавляем собственные расширения

1. Добавляем класс `App\Application\Twig\MyTwigExtension`
    ```php
    <?php
    
    namespace App\Application\Twig;
    
    use Twig\Extension\AbstractExtension;
    use Twig\TwigFilter;
    use Twig\TwigFunction;
    
    class MyTwigExtension extends AbstractExtension
    {
        public function getFilters(): array
        {
            return [
                new TwigFilter('my_twice', static fn ($val) => $val.$val),
            ];
        }

        public function getFunctions(): array
        {
            return [
                new TwigFunction('my_greet', static fn ($val) => 'Hello, '.$val),
            ];
        }
    }
    ```
2. Исправляем файл `templates/macros.twig`
    ```html
    {% macro user_table_body(users) %}
        {% for user in users %}
            <tr><td>{{ user.id|my_twice }}</td><td>{{ user.login }}</td><td>{{ user.communicationChannel }}</td><td>({{ user.communicationMethod }})</td></tr>
        {% endfor %}
    {% endmacro %}
    ```
3. Исправляем файл `templates/user-table.twig`
    ```html
    {% extends 'layout.twig' %}
    
    {% import 'macros.twig' as macros %}
    
    {% block title %}
        {{ my_greet('User') }}
    {% endblock %}
    {% block body %}
        <table>
            <tbody>
            <tr><th>ID</th><th>Логин</th><th>Канал коммуникации</th><th>Адрес</th></tr>
            {{ macros.user_table_body(users) }}
            </tbody>
        </table>
    {% endblock %}
    ```
4. Обновляем страницу в браузере, видим работу функции и фильтра в расширении

## Устанавливаем Webpack Encore и подключаем bootstrap

1. Устанавливаем пакет `symfony/webpack-encore-bundle`
2. Устанавливаем `yarn` в контейнере (либо добавляем в образ, если планируем использовать дальше) командой
   `apk add yarn`
3. Устанавливаем зависимости командой `yarn install`
4. Устанавливаем загрузчик для работы с SASS командой `yarn add sass-loader@^14.0.0 node-sass --dev`
5. Устанавливаем bootstrap командой `yarn add @popperjs/core bootstrap --dev`
6. Переименовываем файл `assets/styles/app.css` в `app.scss` и исправляем его
    ```scss
    @import "~bootstrap/scss/bootstrap";
    
    body {
        background-color: lightgray;
    }
    ```
7. Исправляем файл `assets/app.js`
    ```js
    import './styles/app.scss';
    require('bootstrap');
    ```
8. Исправляем файл `webpack.config.js`, убираем комментарий в строке 57 (`//.enableSassLoader()`)
9. Исправляем файл `templates/user-table.twig`
    ```html
    {% extends 'layout.twig' %}
   
    {% import 'macros.twig' as macros %}
   
    {% block title %}
    {{ my_greet('User') }}
    {% endblock %}
    {% block body %}
    <table class="table table-hover">
        <tbody>
            <tr><th>ID</th><th>Логин</th><th>Канал коммуникации</th><th>Адрес</th></tr>
            {{ macros.user_table_body(users) }}
        </tbody>
    </table>
    {% endblock %}
    ```
10. В файле `templates/layout.twig` убираем комментарии с вызовов макросов для загрузки CSS и JS
     ```html
     <!DOCTYPE html>
     <html>
         <head>
             <meta charset="UTF-8">
             <title>{% block title %}Welcome!{% endblock %}</title>
             {# Run `composer require symfony/webpack-encore-bundle`
                and uncomment the following Encore helpers to start using Symfony UX #}
             {% block stylesheets %}
                 {{ encore_entry_link_tags('app') }}
             {% endblock %}
    
             {% block javascripts %}
                 {{ encore_entry_script_tags('app') }}
             {% endblock %}
         </head>
         <body>
             {% block body %}{% endblock %}
             {% block footer %}{% endblock %}
         </body>
     </html>   
     ```
11. Выполняем сборку для dev-окружения командой `yarn encore dev`
12. Видим собранные файлы в директории `public/build`
13. Выполняем сборку для prod-окружения командой `yarn encore production`
14. Видим собранные файлы в директории `public/build`, которые обфусцированы и содержат хэш в имени
15. Обновляем страницу в браузере, видим, что таблица отображается в boostrap-стиле

## Добавляем форму для создания и редактирования пользователя

1. Заходим в контейнер `php` командой `docker exec -it php sh`. Дальнейшие команды выполняются из контейнера
2. Устанавливаем пакет `symfony/form`
3. В классе `App\Domain\Entity\User` добавляем поля и геттеры/сеттеры для них
    ```php
    #[ORM\Column(type: 'string', nullable: false)]
    private string $password;

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $age;

    #[ORM\Column(type: 'boolean', nullable: false)]
    private bool $isActive;

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
    ```
4. Выполняем команду `php bin/console doctrine:migrations:diff`
5. Очищаем таблицу `user`, чтобы миграция смогла примениться
6. Проверяем сгенерированную миграцию и применяем её с помощью команды `php bin/console doctrine:migrations:migrate`
7. Исправляем класс `App\Domain\Model\CreateUserModel`
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
            public readonly string $password = 'myPass',
            public readonly int $age = 18,
            public readonly bool $isActive = true,
        ) {
        }
    }
    ```
8. В классе `App\Domain\Service\UserService` исправляем метод `create`
    ```php
    public function create(CreateUserModel $createUserModel): User
    {
        $user = match($createUserModel->communicationChannel) {
            CommunicationChannelEnum::Email => (new EmailUser())->setEmail($createUserModel->communicationMethod),
            CommunicationChannelEnum::Phone => (new PhoneUser())->setPhone($createUserModel->communicationMethod),
        };
        $user->setLogin($createUserModel->login);
        $user->setPassword($createUserModel->password);
        $user->setAge($createUserModel->age);
        $user->setIsActive($createUserModel->isActive);
        $this->userRepository->create($user);

        return $user;
    }
    ```
9. Добавляем класс `App\Controller\Form\PhoneUserType`
    ```php
    <?php
    
    namespace App\Controller\Form;
    
    use App\Domain\Entity\PhoneUser;
    use Symfony\Component\Form\AbstractType;
    use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
    use Symfony\Component\Form\Extension\Core\Type\IntegerType;
    use Symfony\Component\Form\Extension\Core\Type\PasswordType;
    use Symfony\Component\Form\Extension\Core\Type\SubmitType;
    use Symfony\Component\Form\Extension\Core\Type\TextType;
    use Symfony\Component\Form\FormBuilderInterface;
    use Symfony\Component\OptionsResolver\OptionsResolver;
    
    class PhoneUserType extends AbstractType
    {
        public function buildForm(FormBuilderInterface $builder, array $options): void
        {
            $builder
                ->add('login', TextType::class, [
                    'label' => 'Логин пользователя',
                    'attr' => [
                        'data-time' => time(),
                        'placeholder' => 'Логин пользователя',
                        'class' => 'user-login',
                    ],
                ]);
    
            if ($options['isNew'] ?? false) {
                $builder->add('password', PasswordType::class, [
                    'label' => 'Пароль пользователя',
                ]);
            }
    
            $builder
                ->add('phone', TextType::class, [
                    'label' => 'Телефон',
                ])
                ->add('age', IntegerType::class, [
                    'label' => 'Возраст',
                ])
                ->add('isActive', CheckboxType::class, [
                    'required' => false,
                ])
                ->add('submit', SubmitType::class);
        }
    
        public function configureOptions(OptionsResolver $resolver): void
        {
            $resolver->setDefaults([
                'data_class' => PhoneUser::class,
                'empty_data' => new PhoneUser(),
                'isNew' => false,
            ]);
        }
    
        public function getBlockPrefix(): string
        {
            return 'save_user';
        }
    }
     ```
10. В классе `App\Domain\Service\UserService` добавляем метод `createFromForm`
     ```php
     public function processFromForm(User $user): void
     {
         $this->userRepository->create($user);
     }
     ```
11. Добавляем класс `App\Controller\web\PhoneUserForm\v1\Manager`
     ```php
     <?php
    
     namespace App\Controller\Web\PhoneUserForm\v1;
    
     use App\Controller\Form\PhoneUserType;
     use App\Domain\Entity\User;
     use App\Domain\Service\UserService;
     use Symfony\Component\Form\FormFactoryInterface;
     use Symfony\Component\HttpFoundation\Request;
    
     class Manager
     {
         public function __construct(
             private readonly UserService $userService,
             private readonly FormFactoryInterface $formFactory,
         ) {
         }
        
         public function getFormData(Request $request, ?User $user = null): array
         {
             $isNew = $user === null;
             $form = $this->formFactory->create(PhoneUserType::class, $user, ['isNew' => $isNew]);
             $form->handleRequest($request);
    
             if ($form->isSubmitted() && $form->isValid()) {
                 /** @var User $user */
                 $user = $form->getData();
                 $this->userService->processFromForm($user);
             }
    
             return [
                 'form' => $form,
                 'isNew' => $isNew,
                 'user' => $user,
             ];
         }
     }
     ```
12. Добавляем класс `App\Controller\Web\PhoneUserForm\v1\CreateController`
     ```php
     <?php
    
     namespace App\Controller\Web\PhoneUserForm\v1;
    
     use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
     use Symfony\Component\HttpFoundation\Request;
     use Symfony\Component\HttpFoundation\Response;
     use Symfony\Component\Routing\Attribute\Route;
    
     class CreateController extends AbstractController
     {
         public function __construct(private readonly Manager $manager)
         {
         }
    
         #[Route(path: '/api/v1/create-phone-user', methods: ['GET', 'POST'])]
         public function manageUserAction(Request $request): Response
         {
             return $this->render('phone-user.twig', $this->manager->getFormData($request));
         }
     }    
     ```
13. Добавляем класс `App\Controller\Web\PhoneUserForm\v1\EditController`
     ```php
     <?php
    
     namespace App\Controller\Web\PhoneUserForm\v1;
    
     use App\Domain\Entity\User;
     use Symfony\Bridge\Doctrine\Attribute\MapEntity;
     use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
     use Symfony\Component\HttpFoundation\Request;
     use Symfony\Component\HttpFoundation\Response;
     use Symfony\Component\Routing\Attribute\Route;
    
     class EditController extends AbstractController
     {
         public function __construct(private readonly Manager $manager)
         {
         }
    
         #[Route(path: '/api/v1/update-phone-user/{id}', methods: ['GET', 'POST'])]
         public function manageUserAction(Request $request, #[MapEntity(id: 'id')] User $user): Response
         {
             return $this->render('phone-user.twig', $this->manager->getFormData($request, $user));
         }
     }
     ```
14. Добавляем файл `src/templates/phone-user.twig`
     ```html
     {% extends 'layout.twig' %}
   
     {% block body %}
         <div>
             {{ form(form) }}
         </div>
     {% endblock %}
     ```
15. В браузере переходим по адресу `http://localhost:7777/api/v1/create-phone-user`, видим форму
16. Вводим данные, отправляем и проверяем базу. Данные должны быть сохранены
17. Берем в качестве ID последнего созданного пользователя, переходим по адресу `http://localhost:7777/api/v1/update-phone-user/ID`,
    проверяем работоспособность сохранения.

## Добавляем boostrap в форму

1. Исправляем файл `templates/phone-user.twig`
    ```html
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        {% block head_css %}
            <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/1.1.1/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">
        {% endblock %}
    </head>
    <body>
    {% form_theme form 'bootstrap_4_layout.html.twig' %}
    <div style="width:50%;margin-left:10px;margin-top:10px">
        {{ form(form) }}
    </div>
    {% block head_js %}
        <script src="https://code.jquery.com/jquery-3.4.1.slim.min.js" integrity="sha384-J6qa4849blE2+poT4WnyKhv5vZF5SrPo0iEjwBvKU7imGFAV0wwj1yYfoRSJoZ+n" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.4.1/dist/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">
    {% endblock %}
    </body>
    </html>
    ```
2. Переходим по адресу `http://localhost:7777/api/v1/create-phone-user`, видим более красивый вариант формы

## Меняем HTTP-метод для редактирования

1. В классе `App\Controller\Web\PhoneUserForm\v1\EditController` исправляем атрибут для метода `__invoke`
    ```php
    #[Route(path: '/api/v1/update-phone-user/{id}', methods: ['GET', 'PATCH'])]
    ```
2. В классе `App\Controller\Form\PhoneUserType` исправляем метод `buildForm`
    ```php
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('login', TextType::class, [
                'label' => 'Логин пользователя',
                'attr' => [
                    'data-time' => time(),
                    'placeholder' => 'Логин пользователя',
                    'class' => 'user-login',
                ],
            ]);

        if ($options['isNew'] ?? false) {
            $builder->add('password', PasswordType::class, [
                'label' => 'Пароль пользователя',
            ]);
        }

        $builder
            ->add('phone', TextType::class, [
                'label' => 'Телефон',
            ])
            ->add('age', IntegerType::class, [
                'label' => 'Возраст',
            ])
            ->add('isActive', CheckboxType::class, [
                'required' => false,
            ])
            ->add('submit', SubmitType::class)
            ->setMethod($options['isNew'] ? 'POST' : 'PATCH');
    }
    ```
3. Исправляем файл `public/index.php`
    ```php
    <?php
   
    use App\Kernel;
    use Symfony\Component\HttpFoundation\Request;
       
    require_once dirname(__DIR__).'/vendor/autoload_runtime.php';
       
    return function (array $context) {
        Request::enableHttpMethodParameterOverride();

        return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
    };
    ```
4. Берем в качестве ID последнего созданного пользователя, переходим по адресу `http://localhost:7777/api/v1/update-phone-user/ID`,
   проверяем работоспособность сохранения.

## Добавляем DTO с валидацией

1. Добавляем класс `App\Controller\Web\UserForm\v1\Input\CreateUserDTO`
    ```php
    <?php
    
    namespace App\Controller\Web\UserForm\v1\Input;
    
    use Symfony\Component\Validator\Constraints as Assert;
    
    #[Assert\Expression(
        expression: '(this.email === null and this.phone !== null) or (this.phone === null and this.email !== null)',
        message: 'Eiteher email or phone should be provided',
    )]
    class CreateUserDTO
    {
        public function __construct(
            #[Assert\NotBlank]
            public ?string $login = null,
            public ?string $email = null,
            #[Assert\Length(max: 20)]
            public ?string $phone = null,
            #[Assert\NotBlank]
            public ?string $password = '',
            public ?int $age = 18,
            public ?bool $isActive = false,
        ) {
        }
    }
    ```
2. Добавляем класс `App\Controller\Form\UserType`
    ```php
    <?php
    
    namespace App\Controller\Form;
    
    use App\Controller\Web\UserForm\v1\Input\CreateUserDTO;
    use Symfony\Component\Form\AbstractType;
    use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
    use Symfony\Component\Form\Extension\Core\Type\IntegerType;
    use Symfony\Component\Form\Extension\Core\Type\PasswordType;
    use Symfony\Component\Form\Extension\Core\Type\SubmitType;
    use Symfony\Component\Form\Extension\Core\Type\TextType;
    use Symfony\Component\Form\FormBuilderInterface;
    use Symfony\Component\OptionsResolver\OptionsResolver;
    
    class UserType extends AbstractType
    {
        public function buildForm(FormBuilderInterface $builder, array $options): void
        {
            $builder
                ->add('login', TextType::class, [
                    'label' => 'Логин пользователя',
                    'attr' => [
                        'data-time' => time(),
                        'placeholder' => 'Логин пользователя',
                        'class' => 'user-login',
                    ],
                ]);
    
            if ($options['isNew'] ?? false) {
                $builder->add('password', PasswordType::class, [
                    'label' => 'Пароль пользователя',
                ]);
            }
    
            $builder
                ->add('phone', TextType::class, [
                    'label' => 'Телефон',
                    'required' => false,
                ])
                ->add('email', TextType::class, [
                    'label' => 'E-mail',
                    'required' => false,
                ])
                ->add('age', IntegerType::class, [
                    'label' => 'Возраст',
                ])
                ->add('isActive', CheckboxType::class, [
                    'required' => false,
                ])
                ->add('submit', SubmitType::class)
                ->setMethod($options['isNew'] ? 'POST' : 'PATCH');
        }
    
        public function configureOptions(OptionsResolver $resolver): void
        {
            $resolver->setDefaults([
                'data_class' => CreateUserDTO::class,
                'empty_data' => new CreateUserDTO(),
                'isNew' => false,
            ]);
        }
    
        public function getBlockPrefix(): string
        {
            return 'save_user';
        }
    }
    ```
3. Добавляем класс `App\Controller\Web\UserForm\v1\Manager`
   ```php
   <?php
    
   namespace App\Controller\Web\UserForm\v1;
    
   use App\Controller\Form\UserType;
   use App\Controller\Web\UserForm\v1\Input\CreateUserDTO;
   use App\Domain\Entity\EmailUser;
   use App\Domain\Entity\PhoneUser;
   use App\Domain\Entity\User;
   use App\Domain\Model\CreateUserModel;
   use App\Domain\Service\ModelFactory;
   use App\Domain\Service\UserService;
   use App\Domain\ValueObject\CommunicationChannelEnum;
   use Symfony\Component\Form\FormFactoryInterface;
   use Symfony\Component\HttpFoundation\Request;
    
   class Manager
   {
       public function __construct(
           private readonly UserService $userService,
           private readonly FormFactoryInterface $formFactory,
           private readonly ModelFactory $modelFactory,
       ) {
       }
    
       public function getFormData(Request $request, ?User $user = null): array
       {
           $isNew = $user === null;
           $formData = $isNew ? null : new CreateUserDTO(
               $user->getLogin(),
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
   }
   ```
4. Добавляем класс `App\Controller\Web\UserForm\v1\CreateController`
    ```php
    <?php
    
    namespace App\Controller\Web\UserForm\v1;
    
    use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\Routing\Attribute\Route;
    
    class CreateController extends AbstractController
    {
        public function __construct(private readonly Manager $manager)
        {
        }
    
        #[Route(path: '/api/v1/creat-user', methods: ['GET', 'POST'])]
        public function manageUserAction(Request $request): Response
        {
            return $this->render('phone-user.twig', $this->manager->getFormData($request));
        }
    }
    ```
5. Добавляем класс `App\Controller\Web\UserForm\v1\EditController`
    ```php
    <?php
    
    namespace App\Controller\Web\UserForm\v1;
    
    use App\Domain\Entity\User;
    use Symfony\Bridge\Doctrine\Attribute\MapEntity;
    use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\Routing\Attribute\Route;
    
    class EditController extends AbstractController
    {
        public function __construct(private readonly Manager $manager)
        {
        }
    
        #[Route(path: '/api/v1/update-user/{id}', methods: ['GET', 'PATCH'])]
        public function manageUserAction(Request $request, #[MapEntity(id: 'id')] User $user): Response
        {
            return $this->render('phone-user.twig', $this->manager->getFormData($request, $user));
        }
    }
    ```
6. Переходим по адресу `http://localhost:7777/api/v1/create-user`, создаём пользователя
7. Берем в качестве ID последнего созданного пользователя, переходим по адресу `http://localhost:7777/api/v1/update-user/ID`,
   проверяем работоспособность сохранения.
