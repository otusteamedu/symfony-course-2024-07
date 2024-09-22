# Внедряем GraphQL

Запускаем контейнеры командой `docker-compose up -d`

## Устанавливаем GraphQL

1. Устанавливаем пакет `webonyx/graphql-php`
2. В файле `confgi/packages/security.yaml` в секции `security.firewalls.main` добавляем `security: false`
3. Добавляем несколько пользователей с телефонами с помощью запроса Add user v2 из Postman-коллекции v6
4. Заходим по адресу http://localhost:7777/api-platform/graphql, видим GraphQL-песочницу
5. Делаем в ней запрос
    ```
    {
      users {
        edges {
          node {
            id
            _id
            login
          }
        }
      }
    } 
    ```

## Получим связанные сущности

1. В классе `App\Domain\Entity\Subscription` добавляем атрибут `#[ApiResource]`
2. Добавляем класс `App\Controller\Web\CreateSubscription\v1\Manager`
    ```php
    <?php
    
    namespace App\Controller\Web\CreateSubscription\v1;
    
    use App\Domain\Entity\User;
    use App\Domain\Service\SubscriptionService;
    
    class Manager
    {
        public function __construct(private readonly SubscriptionService $subscriptionService)
        {
        }
        
        public function create(User $author, User $follower): void
        {
            $this->subscriptionService->addSubscription($author, $follower);
        }
    }
    ```
3. Добавляем класс `App\Controller\Web\CreateSubscription\v1\Controller`
    ```php
    <?php
    
    namespace App\Controller\Web\CreateSubscription\v1;
    
    use App\Domain\Entity\User;
    use Symfony\Bridge\Doctrine\Attribute\MapEntity;
    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\HttpKernel\Attribute\AsController;
    use Symfony\Component\Routing\Attribute\Route;
    
    #[AsController]
    class Controller
    {
        public function __construct(
            private readonly Manager $manager,
        ) {
        }
    
        #[Route(
            path: 'api/v1/create-subscription/{author}/{follower}',
            requirements: ['author' => '\d+', 'follower' => '\d+'],
            methods: ['POST'])
        ]
        public function __invoke(#[MapEntity(id: 'author')] User $author, #[MapEntity(id: 'follower')] User $follower): Response
        {
            $this->manager->create($author, $follower);
            
            return new JsonResponse(null, Response::HTTP_CREATED);
        }
    }
    ```
4. Выполняем запрос Add subscription из Postman-коллекции v6 с идентификаторами добавленных ранее пользователей, чтобы
   сделать одному из них несколько подписок.
5. В классе `App\Domain\Entity\User`
   1. добавляем метод `getSubscriptionFollowers`
       ```php
       /**
        * @return Subscription[]
        */
       public function getSubscriptionFollowers(): array
       {
           return $this->subscriptionFollowers->toArray();
       }
       ```
   2. удаляем атрибут класса `#[Get(output: CreatedUserDTO::class, provider: UserProviderDecorator::class)]`
6. В классе `App\Domain\Entity\PhoneUser` добавляем атрибут `#[ApiResourse]`
7. В браузере перезапускаем страницу с GraphQL-песочницей и делаем запрос для получения пользователей и id их подписчиков
    ```
    {
      phoneUsers {
        edges {
          node {
            id
            _id
            login
            phone
            subscriptionFollowers {
              edges {
                node {
                  id
                }
              }
            }
          }
        }
      }
    }
    ```
8. Добавляем ограничение и метаинформацию
    ```
    {
      phoneUsers {
        edges {
          node {
            id
            _id
            login
            phone
            subscriptionFollowers(first: 3) {
              totalCount
              edges {
                node {
                  id
                }
                cursor
              }
              pageInfo {
                endCursor
                hasNextPage
              }
            }
          }
        }
      }
    }
    ```
9. Для получения следующей страницы добавляем параметр `after`
    ```
    {
      phoneUsers {
        edges {
          node {
            id
            _id
            login
            phone
            subscriptionFollowers(first: 3 after: "Mg==") {
              totalCount
              edges {
                node {
                  id
                }
                cursor
              }
              pageInfo {
                endCursor
                hasNextPage
              }
            }
          }
        }
      }
    }
    ```
10. Раскрываем дополнительно информацию о подписке ещё на один уровень
     ```
     {
       phoneUsers {
         edges {
           node {
             id
             _id
             login
             phone
             subscriptionFollowers(first: 3 after: "Mg==") {
               edges {
                 node {
                   follower {
                     id
                     _id
                     login
                   }
                 }
               }
               pageInfo {
                 endCursor
                 hasNextPage
               }
             }
           }
         }
       }
     }
     ```
11. Получаем данные о пользователе через фильтр по id
     ```
     {
       phoneUser(id: "/api-platform/phone_users/1") {
         id
         _id
         login
         phone
         subscriptionFollowers(first: 3, after: "Mg==") {
           edges {
             node {
               follower {
                 id
                 _id
                 login
               }
             }
           }
           pageInfo {
             endCursor
             hasNextPage
           }
         }
       }
     }
     ```

## Добавляем фильтрацию

1. К классу `App\Domain\Entity\PhoneUser` добавляем атрибут для фильтрации
    ```php
    #[ApiFilter(SearchFilter::class, properties: ['login' => 'partial'])]
    ```
2. В браузере перезапускаем страницу с GraphQL-песочницей и получаем данные о пользователях с фильтром
    ```
    {
      phoneUsers(login: "user3") {
        edges {
          node {
            id
            _id
            login
            phone
          }
        }
      }
    }
    ```

## Добавляем сортировку

1. К классу `App\Domain\Entity\PhoneUser` добавляем атрибут для сортировки
    ```php
    #[ApiFilter(OrderFilter::class, properties: ['login'])]
    ```
2. В браузере перезапускаем страницу с GraphQL-песочницей и получаем данные о пользователях с сортировкой
    ```
    {
      phoneUsers(first: 3 order: { login: "DESC" }) {
        edges {
          node {
            _id
            login
            phone
          }
        }
      }
    }
    ```

## Добавляем поиск по вложенному полю

1. К классу `App\Domain\Entity\Subscription` добавляем атрибут для поиска по вложенному полю 
    ```php
    #[ApiFilter(SearchFilter::class, properties: ['follower.login' => 'partial'])]
    ```
2. В браузере перезапускаем страницу с GraphQL-песочницей и получаем данные с фильтрацией подзапроса
    ```
    {
      phoneUser(id: "/api-platform/phone_users/1") {
        _id
        login
        subscriptionFollowers(follower_login: "user3") {
          edges {
            node {
              follower {
                _id
                login
              }
            }
          }
        }
      }
    }
    ```

## Работаем с мутаторами

1. Добавим пользователя запросом
    ```
    mutation CreatePhoneUser($login:String!, $password:String!, $age:Int!, $phone:String!) {
      createPhoneUser(input:{login:$login, password:$password, age:$age, isActive:true, roles:[], phone:$phone}) {
        phoneUser {
          _id
        }
      }
    }
    ```
   с переменными
    ```json
    {
      "login":"graphql_user",
      "password": "graphql_password",
      "age": 35,
      "phone": "+1234567890"
    }
    ```
2. Изменим пользователя запросом
    ```
    mutation UpdatePhoneUser($id:ID!, $login:String!, $password:String!, $age:Int!, $phone:String!) {
      updatePhoneUser(input:{id:$id, login:$login, password:$password, age:$age, phone:$phone}) {
        phoneUser {
          _id
          login
          password
          age
          phone
        }
      }
    }
    ```
   с переменными
    ```json
    {
      "id":"/api-platform/phone_users/3",
      "login":"new_graphql_user",
      "password": "new_graphql_password",
      "age": 135,
      "phone": "+0987654321"
    }
    ```
3. Удалим пользователя запросом
    ```
    mutation DeletePhoneUser($id:ID!) {
      deletePhoneUser(input:{id:$id}) {
        phoneUser {
          id
        }
      }
    }
    ```
   с переменными
    ```json
    {
      "id":"/api-platform/phone_users/ID"
    }
    ```
   где ID – идентификатор добавленного через GraphQL пользователя.
 
## Делаем кастомный резолвер коллекций
 
1. В класс `App\Domain\Entity\User` добавим новое поле, геттер и сеттер
    ```php
    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $isProtected;

    public function isProtected(): bool
    {
        return $this->isProtected ?? false;
    }

    public function setIsProtected(bool $isProtected): void
    {
        $this->isProtected = $isProtected;
    }
    ```
2. Генерируем миграцию командой `php bin/console doctrine:migrations:diff`
3. Проверяем сгенерированную миграцию и применяем её с помощью команды `php bin/console doctrine:migrations:migrate`
4. Добавляем класс `App\Domain\ApiPlatform\GraphQL\Resolver\UserCollectionResolver`
    ```php
    <?php
    
    namespace App\Domain\ApiPlatform\GraphQL\Resolver;
    
    use ApiPlatform\GraphQl\Resolver\QueryCollectionResolverInterface;
    use App\Domain\Entity\User;
    
    class UserCollectionResolver implements QueryCollectionResolverInterface
    {
        private const MASK = '****';
    
        /**
         * @param iterable<User> $collection
         * @param array $context
         *
         * @return iterable<User>
         */
        public function __invoke(iterable $collection, array $context): iterable
        {
            /** @var User $user */
            foreach ($collection as $user) {
                if ($user->isProtected()) {
                    $user->setLogin(self::MASK);
                    $user->setPassword(self::MASK);
                }
            }
    
            return $collection;
        }
    } 
    ```
5. В классе `App\Domain\Entity\User` исправляем атрибут `#[ApiResource]`
    ```php
    #[ApiResource(
        graphQlOperations: [new QueryCollection(resolver: UserCollectionResolver::class, name: 'protected')]
    )]
    ```
6. Изменяем в БД у пользователя с подписчиками значение поля `is_protected` на `true`
7. В браузере перезапускаем страницу с GraphQL-песочницей и получаем данные новым запросом
    ```
    {
      protectedUsers {
        edges {
          node {
            _id
            login
            password
          }
        }
      }
    }
    ```

## Делаем кастомный резолвер одной сущности

1. Добавляем класс `App\Domain\ApiPlatform\GraphQL\Resolver\UserResolver`
    ```php
    <?php
    
    namespace App\Domain\ApiPlatform\GraphQL\Resolver;
    
    use ApiPlatform\GraphQl\Resolver\QueryItemResolverInterface;
    use App\Domain\Entity\User;
    
    class UserResolver implements QueryItemResolverInterface
    {
        private const MASK = '****';
    
        /**
         * @param User|null $item
         */
        public function __invoke($item, array $context): User
        {
            if ($item->isProtected()) {
                $item->setLogin(self::MASK);
                $item->setPassword(self::MASK);
            }
    
            return $item;
        }
    }
    ```
2. В классе `App\Domain\Entity\User` исправляем атрибут `#[ApiResource]`
    ```php
    #[ApiResource(
        graphQlOperations: [
            new Query(),
            new QueryCollection(),
            new QueryCollection(resolver: UserCollectionResolver::class, name: 'protected'),
            new Query(resolver: UserResolver::class, name: 'protected')
        ]
    )]
    ```
3. В браузере перезапускаем страницу с GraphQL-песочницей и получаем данные новым запросом
    ```
    {
      protectedUser(id: "/api-platform/users/1") {
        _id
        login
        password
      }
    }
    ```
 
## Добавляем фильтрацию в кастомный резолвер
 
1. Исправляяем класс `App\Domain\ApiPlatform\GraphQL\Resolver\UserResolver`
    ```php
    <?php
    
    namespace App\Domain\ApiPlatform\GraphQL\Resolver;
    
    use ApiPlatform\GraphQl\Resolver\QueryItemResolverInterface;
    use App\Domain\Entity\User;
    use App\Domain\Service\UserService;
    
    class UserResolver implements QueryItemResolverInterface
    {
        private const MASK = '****';
        
        public function __construct(private readonly UserService $userService) {
        }
    
        /**
         * @param User|null $item
         */
        public function __invoke($item, array $context): User
        {
            if (isset($context['args']['_id'])) {
                $item = $this->userService->findUserById($context['args']['_id']);
            } elseif (isset($context['args']['login'])) {
                $item = $this->userService->findUserByLogin($context['args']['login']);
            }
    
            if ($item->isProtected()) {
                $item->setLogin(self::MASK);
                $item->setPassword(self::MASK);
            }
    
            return $item;
        }
    }
    ```
2. В классе `App\Domain\Entity\User` исправляем атрибут `#[ApiResource]`
    ```php
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
    ```
3. В браузере перезапускаем страницу с GraphQL-песочницей и получаем пользователя по логину
    ```
    {
      protectedUser(login: "my_user") {
        _id
        login
        password
      }
    }
    ```
4. В браузере перезапускаем страницу с GraphQL-песочницей и получаем пользователя по id
    ```
    {
      protectedUser(_id: 1) {
        _id
        login
        password
      }
    }
    ```
