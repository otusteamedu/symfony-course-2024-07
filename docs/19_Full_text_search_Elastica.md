# Полнотекстовый поиск, Elastica

## Установка elastica-bundle

1. Устанавливаем пакет `friendsofsymfony/elastica-bundle`
2. В файле `.env` исправляем DSN для ElasticSearch
    ```shell script
    ELASTICSEARCH_URL=http://elasticsearch:9200/
    ```
3. Выполняем запрос Add user v2 из Postman-коллекции  v10
4. Выполняем запрос Add followers из Postman-коллекции  v10, чтобы получить побольше записей в БД
5. В файле `config/packages/fos_elastica.yaml` в секции `fos_elastica.indexes` удаляем `app` и добавляем секцию `user`:
    ```yaml
    user:
        persistence:
            driver: orm
            model: App\Domain\Entity\User
        properties:
            login: ~
            age: ~
            phone: ~
            email: ~
    ```
6. Заполняем индекс командой `php bin/console fos:elastica:populate`
7. Заходим в Kibana по адресу `http://localhost:5601`
8. В Kibana заходим в Stack Management -> Index patterns и создаём index pattern на базе индекса `user`
9. Переходим в `Discover`, видим наши данные в новом шаблоне, причём телефон и email корректно заполняются

## Добавляем кастомное свойство

1. Добавляем класс `App\Application\Elastica\UserPropertyListener`
    ```php
    <?php
    
    namespace App\Application\Elastica;
    
    use App\Domain\Entity\EmailUser;
    use App\Domain\Entity\User;
    use App\Domain\ValueObject\CommunicationChannelEnum;
    use FOS\ElasticaBundle\Event\PostTransformEvent;
    use Symfony\Component\EventDispatcher\EventSubscriberInterface;
    
    class UserPropertyListener implements EventSubscriberInterface
    {
        public static function getSubscribedEvents()
        {
            return [
                PostTransformEvent::class => 'addCommunicationMethodProperties'
            ];
        }
    
        public function addCommunicationMethodProperties(PostTransformEvent $event): void
        {
            $user = $event->getObject();
            if ($user instanceof User) {
                $document = $event->getDocument();
                $document->set(
                    'communicationMethod',
                    $user instanceof EmailUser ? CommunicationChannelEnum::Email : CommunicationChannelEnum::Phone
                );
            }
        }
    }
    ```
2. Ещё раз заполняем индекс командой `php bin/console fos:elastica:populate`
3. В Kibana обновляем страницу, видим новое поле.

## Используем вложенные документы

1. Выполняем запрос Post tweet из Postman-коллекции  v10, чтобы получить запись в таблице `tweet`
2. Добавим индекс с составными полями в `config/packages/fos_elastica.yaml` в секцию `fos_elastica.indexes`
    ```yaml
    tweet:
        persistence:
            driver: orm
            model: App\Domain\Entity\Tweet
        properties:
            author:
                type: nested
                properties:
                    name:
                        property_path: login
                    age: ~
                    phone: ~
                    email: ~
            text: ~
    ```
3. В контейнере ещё раз заполняем индекс командой `php bin/console fos:elastica:populate`
4. В Kibana заходим в Stack Management -> Index patterns и создаём index pattern на базе индекса `tweet`
5. Переходим в `Discover`, видим наши данные в новом шаблоне

## Используем сериализацию вместо описания схемы

1. В файле `config/packages/fos_elastica.yaml`
    1. Включаем сериализацию
        ```yaml
        serializer: ~
        ```
    2. Для каждого индекса (`user`, `tweet`) удаляем секцию `properties` и добавляем секцию `serializer`
        ```yaml
        serializer:
            groups: [elastica]
        ```
2. В классе `App\Domain\Entity\User` добавляем атрибуты для полей `id`, `login`, `age`
    ```php
    #[ORM\Column(name: 'id', type: 'bigint', unique: true)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[Groups(['elastica'])]
    private ?int $id = null;
   
    #[ORM\Column(type: 'string', length: 32, unique: true, nullable: false)]
    #[Groups(['elastica'])]
    private string $login;

    #[ORM\Column(type: 'integer', nullable: false)]
    #[Groups(['elastica'])]
    private int $age;
    ```
3. В классе `App\Domain\Entity\PhoneUser` добавляем атрибут для поля `phone`
    ```php
    #[ORM\Column(type: 'string', length: 20, nullable: false)]
    #[Groups(['elastica'])]
    private string $phone;
    ```
4. В классе `App\Domain\Entity\EmailUser` добавляем атрибут для поля `email`
    ```php
    #[ORM\Column(type: 'string', nullable: false)]
    #[Groups(['elastica'])]
    private string $email;
    ```
5. В классе `App\Domain\Entity\Tweet` добавляем атрибуты для полей `id`, `author` и `text`
    ```php
    #[ORM\Column(name: 'id', type: 'bigint', unique: true)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[Groups(['elastica'])]
    private ?int $id = null;
   
    #[ORM\ManyToOne(targetEntity: 'User', inversedBy: 'tweets')]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id')]
    #[Groups(['elastica'])]
    private User $author;

    #[ORM\Column(type: 'string', length: 140, nullable: false)]
    #[Groups(['elastica'])]
    private string $text;
    ```
6. Ещё раз заполняем индекс командой `php bin/console fos:elastica:populate`
7. Проверяем в Kibana, что в индексах данные присутствуют

##  Отключаем автообновление индекса

1. Выполняем запрос Add user v2 из Postman-коллекции  v10
2. Проверяем в Kibana, что новая запись появилась в индексе
3. Отключаем listener для insert в файле `config/fos_elastica.yaml` путём добавления секции
   `fos_elastica.indexes.user.persistence.listener`
    ```yaml
    listener:
        insert: false
        update: true
        delete: true
    ```
4. Выполняем ещё один запрос Add user v2 из Postman-коллекции  v10
5. Проверяем в Kibana, что новая запись не появилась в индексе, хотя в БД она есть

### Ищем по индексу

1. В классе `App\Infrastructure\Repository\UserRepository`
    1. добавляем конструктор
        ```php
        public function __construct(
            EntityManagerInterface $entityManager,
            private readonly PaginatedFinderInterface $finder,
        ) {
            parent::__construct($entityManager);
        }
        ```
    2. Добавляем метод `findUsersByQuery`
        ```php
        /**
         * @return User[]
         */
        public function findUsersByQuery(string $query, int $perPage, int $page): array
        {
            $paginatedResult = $this->finder->findPaginated($query);
            $paginatedResult->setMaxPerPage($perPage);
            $paginatedResult->setCurrentPage($page);
    
            return [...$paginatedResult->getCurrentPageResults()];
        }
        ```
2. В файле `config/services.yaml` добавляем новый сервис:
    ```yaml
    App\Infrastructure\Repository\UserRepository:
        arguments:
            $finder: '@fos_elastica.finder.user'
    ```
3. В классе `App\Domain\Service\UserService` добавляем метод `findUsersByQuery`
    ```php
    /**
     * @return User[]
     */
    public function findUsersByQuery(string $query, int $perPage, int $page): array
    {
        return $this->userRepository->findUsersByQuery($query, $perPage, $page);
    }
    ```
4. Добавляем класс `App\Controller\Web\GetUsersByQuery\v1\Manager`
    ```php
    <?php
    
    namespace App\Controller\Web\GetUsersByQuery\v1;
    
    use App\Domain\Entity\User;
    use App\Domain\Service\UserService;
    
    class Manager
    {
        public function __construct(private readonly UserService $userService)
        {
        }
    
        /**
         * @return User[]
         */
        public function findUsersByQuery(string $query, int $perPage, int $page): array
        {
            return $this->userService->findUsersByQuery($query, $perPage, $page);
        }
    }
    ```
5. Добавляем класс `App\Controller\Web\GetUsersByQuery\v1\Controller`
    ```php
    <?php
    
    namespace App\Controller\Web\GetUsersByQuery\v1;
    
    use App\Domain\Entity\User;
    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\HttpKernel\Attribute\AsController;
    use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
    use Symfony\Component\Routing\Attribute\Route;
    
    #[AsController]
    class Controller
    {
        public function __construct(private readonly Manager $manager) {
        }
    
        #[Route(path: 'api/v1/get-users-by-query', methods: ['GET'])]
        public function __invoke(#[MapQueryParameter]string $query, #[MapQueryParameter]int $perPage, #[MapQueryParameter]int $page): Response
        {
            return new JsonResponse(
                [
                    'users' => array_map(
                        static fn (User $user): array => $user->toArray(),
                        $this->manager->findUsersByQuery($query, $perPage, $page)
                    )
                ]
            );
        }
    }
    ```
6. Выполняем несколько запросов Get users by query из Postman-коллекции v10 с данными из разных полей разных
   пользователей

## Делаем нечёткий поиск

1. В классе `App\Infrastructure\Repository\UserRepository` исправляем метод `findUsersByQuery`
    ```php
    /**
     * @return User[]
     */
    public function findUsersByQuery(string $query, int $perPage, int $page): array
    {
        $paginatedResult = $this->finder->findPaginated($query.'~2');
        $paginatedResult->setMaxPerPage($perPage);
        $paginatedResult->setCurrentPage($page);

        return [...$paginatedResult->getCurrentPageResults()];
    }
    ```
2. Выполняем несколько запросов Get users by query из Postman-коллекции v10 с двумя опечатками

## Совмещаем фильтрацию по БД и Elasticsearch

1. Добавляем класс `App\Application\Doctrine\Repository\UserRepository`
    ```php
    <?php
    
    namespace App\Application\Doctrine;
    
    use Doctrine\ORM\EntityRepository;
    use Doctrine\ORM\QueryBuilder;
    
    class UserRepository extends EntityRepository
    {
        public function createIsActiveQueryBuilder(string $alias): QueryBuilder
        {
            return $this->createQueryBuilder($alias)
                ->andWhere("$alias.isActive = :isActive")
                ->setParameter('isActive', true);
        }
    }
    ```
2. В классе `App\Domain\Entity\User` исправляем атрибут
    ```php
    #[ORM\Entity(repositoryClass: UserRepository::class)]
    ```
3. Выполняем команду `php bin/console doctrine:cache:clear-metadata`
4. В файле `config/packages/fos_elastica.yaml` добавляем в секцию `fos_elastica.indexes.user.persistence` новую
   подсекцию
    ```yaml
    elastica_to_model_transformer:
        query_builder_method: createIsActiveQueryBuilder
        ignore_missing: true
    ```
5. Выполняем запрос Get users by query из Postman-коллекции v10 с данными любого пользователя, видим результат
6. Проставляем для этого пользователя в БД `is_active = false`
7. Ещё раз выполняем запрос Get users by query из Postman-коллекции v10 с данными любого пользователя, видим, что
   пользователь не возвращается в ответе
