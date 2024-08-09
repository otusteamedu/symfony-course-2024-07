# DI и сервисы

Запускаем контейнеры командой `docker-compose up -d`

## Добавляем сервис со строковым параметром

1. Создаём класс `App\Domain\Service\GreeterService`
    ```php
    <?php
    
    namespace App\Domain\Service;
    
    class GreeterService
    {
        private string $greet;
    
        public function __construct(string $greet)
        {
            $this->greet = $greet;
        }
    
        public function greet(string $name): string
        {
            return $this->greet.', '.$name.'!';
        }
    }
    ```
2. Исправляем класс `App\Controller\WorldController`
    ```php
    <?php
    
    namespace App\Controller;
    
    use App\Domain\Service\GreeterService;
    use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
    use Symfony\Component\HttpFoundation\Response;
    
    class WorldController extends AbstractController
    {
        public function __construct(private readonly GreeterService $greeterService)
        {
        }
    
        public function hello(): Response
        {
            return new Response("<html><body>{$this->greeterService->greet('world')}</body></html>");
        }
    }
    ```
3. Заходим по адресу `http://localhost:7777/world/hello`, видим ошибку

## Добавляем инъекцию параметра

1. Добавляем в файле `config/services.yaml` новую службу
    ```yaml
    App\Domain\Service\GreeterService:
        arguments:
            $greet: 'Hello'
    ```
2. Заходим по адресу `http://localhost:7777/world/hello`, видим сообщение

## Добавляем FormatService

1. Создаём класс `App\Domain\Service\FormatService`
    ```php
    <?php
    
    namespace App\Domain\Service;
    
    class FormatService
    {
        private ?string $tag;
    
        public function __construct()
        {
            $this->tag = null;
        }
    
        public function setTag(string $tag): self
        {
            $this->tag = $tag;
    
            return $this;
        }
    
        public function format(string $contents): string
        {
            return ($this->tag === null) ? $contents : "<{$this->tag}>$contents</{$this->tag}>";
        }
    }
    ```
2. Создаём класс `App\Domain\Service\FormatServiceFactory`
    ```php
    <?php
    
    namespace App\Domain\Service;
    
    class FormatServiceFactory
    {
        public static function strongFormatService(): FormatService
        {
            return (new FormatService())->setTag('strong');
        }
    
        public function citeFormatService(): FormatService
        {
            return (new FormatService())->setTag('cite');
        }
    
        public function headerFormatService(int $level): FormatService
        {
            return (new FormatService())->setTag("h$level");
        }
    }
    ```
3. Исправляем класс `App\Controller\WorldController`
    ```php
    <?php
    
    namespace App\Controller;
    
    use App\Domain\Service\FormatService;
    use App\Domain\Service\GreeterService;
    use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
    use Symfony\Component\HttpFoundation\Response;
    
    class WorldController extends AbstractController
    {
        public function __construct(
            private readonly FormatService $formatService,
            private readonly GreeterService $greeterService,
        )
        {
        }
    
        public function hello(): Response
        {
            $result = $this->formatService->format($this->greeterService->greet('world'));
    
            return new Response("<html><body>$result</body></html>");
        }
    }
    ```
4. Добавляем новые сервисы форматтеров в файле `config/services.yaml`
    ```yaml
    strong_formatter:
      class: App\Domain\Service\FormatService
      factory: ['App\Domain\Service\FormatServiceFactory', 'strongFormatService']
    
    cite_formatter:
      class: App\Domain\Service\FormatService
      factory: ['@App\Domain\Service\FormatServiceFactory', 'citeFormatService']
    
    main_header_formatter:
      class: App\Domain\Service\FormatService
      factory: ['@App\Domain\Service\FormatServiceFactory', 'headerFormatService']
      arguments: [1]
    ```
5. Заходим по адресу `http://localhost:7777/world/hello`, видим, что никакого форматирования не произошло

## Добавляем инъекцию конкретного форматтера

1. В файле `config/services.yaml` добавляем инъекцию конкретного форматтера в контроллер
    ```yaml
    App\Controller\WorldController:
      arguments:
        $formatService: '@cite_formatter'
    ```
2. Заходим по адресу `http://localhost:7777/world/hello`, видим, что применилось форматирование

## Добавляем сервисы с тэгами и проход компилятора

1. Создаём класс `App\Domain\Service\MessageService`
    ```php
    <?php
    
    namespace App\Domain\Service;
    
    class MessageService
    {
        /** @var GreeterService[] */
        private array $greeterServices;
        /** @var FormatService[] */
        private array $formatServices;
    
        public function __construct()
        {
            $this->greeterServices = [];
            $this->formatServices = [];
        }
    
        public function addGreeter(GreeterService $greeterService): void
        {
            $this->greeterServices[] = $greeterService;
        }
    
        public function addFormatter(FormatService $formatService): void
        {
            $this->formatServices[] = $formatService;
        }
    
        public function printMessages(string $name): string
        {
            $result = '';
            foreach ($this->greeterServices as $greeterService) {
                $current = $greeterService->greet($name);
                foreach ($this->formatServices as $formatService) {
                    $current = $formatService->format($current);
                }
                $result .= $current;
            }
    
            return $result;
        }
    }
    ```
2. Исправляем класс `App\Controller\WorldController`
    ```php
    <?php
    
    namespace App\Controller;
    
    use App\Domain\Service\FormatService;
    use App\Domain\Service\MessageService;
    use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
    use Symfony\Component\HttpFoundation\Response;
    
    class WorldController extends AbstractController
    {
        public function __construct(
            private readonly FormatService $formatService,
            private readonly MessageService $messageService,
        )
        {
        }
    
        public function hello(): Response
        {
            $result = $this->formatService->format($this->messageService->printMessages('world'));
    
            return new Response("<html><body>$result</body></html>");
        }
    }
    ```
3. В файл `config/services.yaml`
    1. Убираем сервис `App\Domain\Service\GreeterService`
    2. Добавляем новые сервисы
         ```yaml
         hello_greeter:
           class: App\Domain\Service\GreeterService
           arguments:
             $greet: 'Hello'
           tags: ['app.greeter_service']
         
         greetings_greeter:
           class: App\Domain\Service\GreeterService
           arguments:
             $greet: 'Greetings'
           tags: ['app.greeter_service']
         
         hi_greeter:
           class: App\Domain\Service\GreeterService
           arguments:
             $greet: 'Hi'
           tags: ['app.greeter_service']
         
         list_formatter:
           class: App\Domain\Service\FormatService
           calls:
             - [setTag, ['ol']]
         
         list_item_formatter:
           class: App\Domain\Service\FormatService
           calls:
             - [setTag, ['li']]
           tags: ['app.formatter_service']
         ```
    3. Добавляем тэг `app.formatter_service` для сервисов `cite_formatter` и `strong_formatter`
    4. Исправляем описание сервиса `App\Controller\WorldController`
         ```yaml
         App\Controller\WorldController:
           arguments:
             $formatService: '@list_formatter'
         ```
4. Создаём класс `App\Application\Symfony\GreeterPass`
    ```php
    <?php
    
    namespace App\Application\Symfony;
    
    use App\Domain\Service\MessageService;
    use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
    use Symfony\Component\DependencyInjection\ContainerBuilder;
    use Symfony\Component\DependencyInjection\Reference;
    
    class GreeterPass implements CompilerPassInterface
    {
        public function process(ContainerBuilder $container): void
        {
            if (!$container->has(MessageService::class)) {
                return;
            }
            $messageService = $container->findDefinition(MessageService::class);
            $greeterServices = $container->findTaggedServiceIds('app.greeter_service');
            foreach ($greeterServices as $id => $tags) {
                $messageService->addMethodCall('addGreeter', [new Reference($id)]);
            }
        }
    }
    ```
5. Создаём класс `App\Application\Symfony\FormatterPass`
    ```php
    <?php
    
    namespace App\Application\Symfony;
    
    use App\Domain\Service\MessageService;
    use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
    use Symfony\Component\DependencyInjection\ContainerBuilder;
    use Symfony\Component\DependencyInjection\Reference;
    
    class FormatterPass implements CompilerPassInterface
    {
        public function process(ContainerBuilder $container): void
        {
            if (!$container->has(MessageService::class)) {
                return;
            }
            $messageService = $container->findDefinition(MessageService::class);
            $formatterServices = $container->findTaggedServiceIds('app.formatter_service');
            foreach ($formatterServices as $id => $tags) {
                $messageService->addMethodCall('addFormatter', [new Reference($id)]);
            }
        }
    }
    ```
6. В класс `App\Kernel` добавляем новый метод `build`
    ```php
    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new FormatterPass());
        $container->addCompilerPass(new GreeterPass());
    }
    ```
7. Заходим по адресу `http://localhost:7777/world/hello`, видим нумерованный список из трёх приветствий с
   форматированием

## Добавляем приоритеты к тэгам

1. В файле `config/services.yaml` изменяем описание тэгов для сервисов приветствий
    ```yaml
    hello_greeter:
      class: App\Domain\Service\GreeterService
      arguments:
        $greet: 'Hello'
      tags:
        - { name: 'app.greeter_service', priority: 3 }
    
    greetings_greeter:
      class: App\Domain\Service\GreeterService
      arguments:
        $greet: 'Greetings'
      tags:
        - { name: 'app.greeter_service', priority: 2 }
    
    hi_greeter:
      class: App\Domain\Service\GreeterService
      arguments:
        $greet: 'Hi'
      tags:
        - { name: 'app.greeter_service', priority: 1 }
    ```
2. Исправляем класс `App\Application\Symfony\GreeterPass`
    ```php
    <?php
    
    namespace App\Application\Symfony;
    
    use App\Domain\Service\MessageService;
    use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
    use Symfony\Component\DependencyInjection\ContainerBuilder;
    use Symfony\Component\DependencyInjection\Reference;
    
    class GreeterPass implements CompilerPassInterface
    {
        public function process(ContainerBuilder $container): void
        {
            if (!$container->has(MessageService::class)) {
                return;
            }
            $messageService = $container->findDefinition(MessageService::class);
            $greeterServices = $container->findTaggedServiceIds('app.greeter_service');
            uasort($greeterServices, static fn(array $tag1, array $tag2) => $tag1[0]['priority'] - $tag2[0]['priority']);
            foreach ($greeterServices as $id => $tags) {
                $messageService->addMethodCall('addGreeter', [new Reference($id)]);
            }
        }
    }
    ```
3. Заходим по адресу `http://localhost:7777/world/hello`, видим пересортированный список
