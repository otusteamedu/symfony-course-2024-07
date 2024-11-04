# Консольные команды в Symfony

Запускаем контейнеры командой `docker-compose up -d`

## Добавляем команду

1. Добавляем класс `App\Controller\Cli\AddFollowersCommand`
    ```php
    <?php
    
    namespace App\Controller\Cli;
    
    use App\Domain\Service\FollowerService;
    use App\Domain\Service\UserService;
    use Symfony\Component\Console\Command\Command;
    use Symfony\Component\Console\Input\InputArgument;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;
    
    final class AddFollowersCommand extends Command
    {
        public function __construct(
            private readonly UserService $userService,
            private readonly FollowerService $followerService
        ) {
            parent::__construct();
        }
    
        protected function configure(): void
        {
            $this->setName('followers:add')
                ->setDescription('Adds followers to author')
                ->addArgument('authorId', InputArgument::REQUIRED, 'ID of author')
                ->addArgument('count', InputArgument::REQUIRED, 'How many followers should be added');
        }
    
        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            $authorId = (int)$input->getArgument('authorId');
            $user = $this->userService->findUserById($authorId);
            if ($user === null) {
                $output->write("<error>User with ID $authorId doesn't exist</error>\n");
                return self::FAILURE;
            }
            $count = (int)$input->getArgument('count');
            if ($count < 0) {
                $output->write("<error>Count should be positive integer</error>\n");
                return self::FAILURE;
            }
    
            $result = $this->followerService->addFollowersSync($user, "Reader #{$authorId}", $count);
    
            $output->write("<info>$result followers were created</info>\n");
    
            return self::SUCCESS;
        }
    } 
    ```
2. Подключаемся в контейнер командой `docker exec -it php sh`. Дальнейшие команды выполняем из контейнера
3. Выполняем запрос Add user v2 из Postman-коллекции v10
4. Выполняем команду `php bin/console`, видим в списке нашу команду
5. Выполняем команду `php bin/console followers:add --help`, видим описание команды и её аргументы
6. Выполняем команду `php bin/console followers:add`, видим ошибку
7. Выполняем команду `php bin/console followers:add 1 100`, видим результат, проверяем, что в БД данные появились

## Делаем аргумент необязательным

1. Исправляем файл `App\Controller\Cli\AddFollowersCommand`
    ```php
    <?php
    
    namespace App\Controller\Cli;
    
    use App\Domain\Service\FollowerService;
    use App\Domain\Service\UserService;
    use Symfony\Component\Console\Command\Command;
    use Symfony\Component\Console\Input\InputArgument;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;
    
    final class AddFollowersCommand extends Command
    {
        private const DEFAULT_FOLLOWERS = 10;
        
        public function __construct(
            private readonly UserService $userService,
            private readonly FollowerService $followerService
        ) {
            parent::__construct();
        }
    
        protected function configure(): void
        {
            $this->setName('followers:add')
                ->setDescription('Adds followers to author')
                ->addArgument('authorId', InputArgument::REQUIRED, 'ID of author')
                ->addArgument('count', InputArgument::OPTIONAL, 'How many followers should be added');
        }
    
        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            $authorId = (int)$input->getArgument('authorId');
            $user = $this->userService->findUserById($authorId);
    
            if ($user === null) {
                $output->write("<error>User with ID $authorId doesn't exist</error>\n");
                return self::FAILURE;
            }
    
            $count = (int)($input->getArgument('count') ?? self::DEFAULT_FOLLOWERS);
            if ($count < 0) {
                $output->write("<error>Count should be positive integer</error>\n");
                return self::FAILURE;
            }
    
            $result = $this->followerService->addFollowersSync($user, "Reader #{$authorId}", $count);
            $output->write("<info>$result followers were created</info>\n");
    
            return self::SUCCESS;
        }
    }    
    ```
2. Выполняем команду `php bin/console followers:add --help`, видим изменившееся описание команды
3. Выполняем команду `php bin/console followers:add 1`, видим ошибку, связанную с дублированием логинов

## Добавляем опцию

1. Исправляем класс `App\Controller\Cli\AddFollowersCommand`
    ```php
    <?php
    
    namespace App\Controller\Cli;
    
    use App\Domain\Service\FollowerService;
    use App\Domain\Service\UserService;
    use Symfony\Component\Console\Command\Command;
    use Symfony\Component\Console\Input\InputArgument;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Input\InputOption;
    use Symfony\Component\Console\Output\OutputInterface;
    
    final class AddFollowersCommand extends Command
    {
        private const DEFAULT_FOLLOWERS = 10;
        private const DEFAULT_LOGIN_PREFIX = 'Reader #';
    
        public function __construct(
            private readonly UserService $userService,
            private readonly FollowerService $followerService
        ) {
            parent::__construct();
        }
    
        protected function configure(): void
        {
            $this->setName('followers:add')
                ->setDescription('Adds followers to author')
                ->addArgument('authorId', InputArgument::REQUIRED, 'ID of author')
                ->addArgument('count', InputArgument::OPTIONAL, 'How many followers should be added')
                ->addOption('login', 'l', InputOption::VALUE_REQUIRED, 'Follower login prefix');
        }
    
        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            $authorId = (int)$input->getArgument('authorId');
            $user = $this->userService->findUserById($authorId);
    
            if ($user === null) {
                $output->write("<error>User with ID $authorId doesn't exist</error>\n");
                return self::FAILURE;
            }
    
            $count = (int)($input->getArgument('count') ?? self::DEFAULT_FOLLOWERS);
            if ($count < 0) {
                $output->write("<error>Count should be positive integer</error>\n");
                return self::FAILURE;
            }
            
            $login = $input->getOption('login') ?? self::DEFAULT_LOGIN_PREFIX;
    
            $result = $this->followerService->addFollowersSync($user, $login.$authorId, $count);
            $output->write("<info>$result followers were created</info>\n");
    
            return self::SUCCESS;
        }
    }
    ```
2. Выполняем команду `php bin/console followers:add --help`, видим изменившееся описание команды
3. Выполняем команды и смотрим на результат
    ```shell script
    php bin/console followers:add 1 --login=login
    php bin/console followers:add 1 --login new_login
    php bin/console followers:add 1 --loginsome_login
    php bin/console followers:add 1 -lwrong_login
    php bin/console followers:add 1 -l=other_login
    php bin/console followers:add 1 -l short_login
    ````

## Прячем команду из списка

1. В классе `App\Controller\Cli\AddFollowersCommand` исправляем метод `configure`
    ```php
    protected function configure(): void
    {
        $this->setName('followers:add')
            ->setHidden()
            ->setDescription('Adds followers to author')
            ->addArgument('authorId', InputArgument::REQUIRED, 'ID of author')
            ->addArgument('count', InputArgument::OPTIONAL, 'How many followers should be added')
            ->addOption('login', 'l', InputOption::VALUE_REQUIRED, 'Follower login prefix');
    }
    ```
2. Выполняем команду `php bin/console`, видим, что нашей команды больше нет в списке
3. Выполняем команду `php bin/console followers:add 1 --login=hidden`, видим, что команда всё ещё работает

## Блокируем параллельный запуск команд

1. Устанавливаем пакет `symfony/lock`
2. Исправляем класс `App\Controller\Cli\AddFollowersCommand`
    ```php
    <?php
    
    namespace App\Controller\Cli;
    
    use App\Domain\Service\FollowerService;
    use App\Domain\Service\UserService;
    use Symfony\Component\Console\Command\Command;
    use Symfony\Component\Console\Command\LockableTrait;
    use Symfony\Component\Console\Input\InputArgument;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Input\InputOption;
    use Symfony\Component\Console\Output\OutputInterface;
    
    final class AddFollowersCommand extends Command
    {
        use LockableTrait;
        
        private const DEFAULT_FOLLOWERS = 10;
        private const DEFAULT_LOGIN_PREFIX = 'Reader #';
    
        public function __construct(
            private readonly UserService $userService,
            private readonly FollowerService $followerService
        ) {
            parent::__construct();
        }
    
        protected function configure(): void
        {
            $this->setName('followers:add')
                ->setHidden()
                ->setDescription('Adds followers to author')
                ->addArgument('authorId', InputArgument::REQUIRED, 'ID of author')
                ->addArgument('count', InputArgument::OPTIONAL, 'How many followers should be added')
                ->addOption('login', 'l', InputOption::VALUE_REQUIRED, 'Follower login prefix');
        }
    
        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            if (!$this->lock()) {
                $output->writeln('<info>Command is already running.</info>');
    
                return self::SUCCESS;
            }
            sleep(100);
            
            $authorId = (int)$input->getArgument('authorId');
            $user = $this->userService->findUserById($authorId);
    
            if ($user === null) {
                $output->write("<error>User with ID $authorId doesn't exist</error>\n");
                return self::FAILURE;
            }
    
            $count = (int)($input->getArgument('count') ?? self::DEFAULT_FOLLOWERS);
            if ($count < 0) {
                $output->write("<error>Count should be positive integer</error>\n");
                return self::FAILURE;
            }
    
            $login = $input->getOption('login') ?? self::DEFAULT_LOGIN_PREFIX;
    
            $result = $this->followerService->addFollowersSync($user, $login.$authorId, $count);
            $output->write("<info>$result followers were created</info>\n");
    
            return self::SUCCESS;
        }
    }
    ```
3. Выполняем команды и видим, что блокировка работает
    ```shell script
    php bin/console followers:add 1 &
    php bin/console followers:add 1
    ```

## Добавляем прогресс-бар

1. В классе `App\Controller\Cli\AddFollowersCommand` исправляем метод `execute`
    ```php
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $authorId = (int)$input->getArgument('authorId');
        $user = $this->userService->findUserById($authorId);

        if ($user === null) {
            $output->write("<error>User with ID $authorId doesn't exist</error>\n");
            return self::FAILURE;
        }

        $count = (int)($input->getArgument('count') ?? self::DEFAULT_FOLLOWERS);
        if ($count < 0) {
            $output->write("<error>Count should be positive integer</error>\n");
            return self::FAILURE;
        }

        $login = $input->getOption('login') ?? self::DEFAULT_LOGIN_PREFIX;

        $result = 0;
        $progressBar = new ProgressBar($output, $count);
        $progressBar->start();
        for ($i = 1; $i <= $count; $i++) {
            $result += $this->followerService->addFollowersSync($user, $login.$authorId.$i, 1);
            usleep(200000);
            $progressBar->advance();
        }
        $progressBar->finish();
        $output->write("\n<info>$result followers were created</info>\n");

        return self::SUCCESS;
    }
    ```
2. Выполняем команду `php bin/console followers:add 1 -lmy_login`, видим заполняющийся прогрессбар

## Добавляем подписку на событие запуска команды

1. Исправляем класс `App\Controller\Cli\AddFollowersCommand`
    ```php
    <?php
    
    namespace App\Controller\Cli;
    
    use App\Domain\Service\FollowerService;
    use App\Domain\Service\UserService;
    use Symfony\Component\Console\Attribute\AsCommand;
    use Symfony\Component\Console\Command\Command;
    use Symfony\Component\Console\Helper\ProgressBar;
    use Symfony\Component\Console\Input\InputArgument;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Input\InputOption;
    use Symfony\Component\Console\Output\OutputInterface;
    
    #[AsCommand(name: self::FOLLOWERS_ADD_COMMAND_NAME, description: 'Add followers to author', hidden: true)]
    final class AddFollowersCommand extends Command
    {
        public const FOLLOWERS_ADD_COMMAND_NAME = 'followers:add';
    
        private const DEFAULT_FOLLOWERS = 10;
        private const DEFAULT_LOGIN_PREFIX = 'Reader #';
    
        public function __construct(
            private readonly UserService $userService,
            private readonly FollowerService $followerService
        ) {
            parent::__construct();
        }
    
        protected function configure(): void
        {
            $this->addArgument('authorId', InputArgument::REQUIRED, 'ID of author')
                ->addArgument('count', InputArgument::OPTIONAL, 'How many followers should be added')
                ->addOption('login', 'l', InputOption::VALUE_REQUIRED, 'Follower login prefix');
        }
    
        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            $authorId = (int)$input->getArgument('authorId');
            $user = $this->userService->findUserById($authorId);
    
            if ($user === null) {
                $output->write("<error>User with ID $authorId doesn't exist</error>\n");
                return self::FAILURE;
            }
    
            $count = (int)($input->getArgument('count') ?? self::DEFAULT_FOLLOWERS);
            if ($count < 0) {
                $output->write("<error>Count should be positive integer</error>\n");
                return self::FAILURE;
            }
    
            $login = $input->getOption('login') ?? self::DEFAULT_LOGIN_PREFIX;
    
            $result = 0;
            $progressBar = new ProgressBar($output, $count);
            $progressBar->start();
            for ($i = 1; $i <= $count; $i++) {
                $result += $this->followerService->addFollowersSync($user, $login.$authorId.$i, 1);
                usleep(200000);
                $progressBar->advance();
            }
            $progressBar->finish();
            $output->write("\n<info>$result followers were created</info>\n");
    
            return self::SUCCESS;
        }
    }
    ```
2. Добавляем класс `App\Application\EventSubscriber\CommandEventSubscriber`
    ```php
    <?php
    
    namespace App\Application\EventSubscriber;
    
    use App\Controller\Cli\AddFollowersCommand;
    use Symfony\Component\Console\ConsoleEvents;
    use Symfony\Component\Console\Event\ConsoleCommandEvent;
    use Symfony\Component\Console\Question\ConfirmationQuestion;
    use Symfony\Component\EventDispatcher\EventSubscriberInterface;
    
    class CommandEventSubscriber implements EventSubscriberInterface
    {
        public static function getSubscribedEvents(): array
        {
            return [
                ConsoleEvents::COMMAND => [['onCommand', 0]],
            ];
        }
    
        public function onCommand(ConsoleCommandEvent $event): void
        {
            $command = $event->getCommand();
            if ($command !== null && $command->getName() === AddFollowersCommand::FOLLOWERS_ADD_COMMAND_NAME) {
                $input = $event->getInput();
                $output = $event->getOutput();
                $helper = $command->getHelper('question');
                $question = new ConfirmationQuestion('Are you sure want to execute this command?(y/n)', false);
                if (!$helper->ask($input, $output, $question)) {
                    $event->disableCommand();
                }
            }
        }
    }
    ```
3. Выполняем команду `php bin/console followers:add 1`, видим дополнительный вопрос, возникающий по событию

## Добавляем тесты для команды

1. В классе `App\Controller\Cli\AddFollowersCommand` исправляем метод `execute`
    ```php
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $authorId = (int)$input->getArgument('authorId');
        $user = $this->userService->findUserById($authorId);

        if ($user === null) {
            $output->write("<error>User with ID $authorId doesn't exist</error>\n");
            return self::FAILURE;
        }

        $count = (int)($input->getArgument('count') ?? self::DEFAULT_FOLLOWERS);
        if ($count < 0) {
            $output->write("<error>Count should be positive integer</error>\n");
            return self::FAILURE;
        }

        $login = $input->getOption('login') ?? self::DEFAULT_LOGIN_PREFIX;

        $result = $this->followerService->addFollowersSync($user, $login.$authorId, $count);
        $output->write("<info>$result followers were created</info>\n");

        return self::SUCCESS;
    }
    ```
2. Удаляем класс `App\Application\EventSubscriber\CommandEventSubscriber`
3. Добавляем класс `UnitTests\Controller\Cli\AddFollowersCommandTest`
    ```php
    <?php
    
    namespace UnitTests\Controller\Cli;
    
    use App\Controller\Cli\AddFollowersCommand;
    use App\Domain\Entity\User;
    use App\Domain\Service\FollowerService;
    use App\Domain\Service\UserService;
    use Mockery;
    use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
    use PHPUnit\Framework\TestCase;
    use Symfony\Component\Console\Tester\CommandTester;
    
    class AddFollowersCommandTest extends TestCase
    {
        use MockeryPHPUnitIntegration;
    
        private const TEST_AUTHOR_ID = 1;
        private const DEFAULT_FOLLOWERS_COUNT = 10;
    
        /**
         * @dataProvider executeDataProvider
         */
        public function testExecuteReturnsResult(?int $followersCount, string $login, string $expected): void
        {
            $authorId = 1;
            $command = $this->prepareCommand($authorId, $login, $followersCount ?? self::DEFAULT_FOLLOWERS_COUNT);
            $commandTester = new CommandTester($command);
    
            $params = ['authorId' => self::TEST_AUTHOR_ID, '--login' => $login];
            if ($followersCount !== null) {
                $params['count'] = $followersCount;
            }
            $commandTester->execute($params);
            $output = $commandTester->getDisplay();
            static::assertSame($expected, $output);
        }
    
        private function prepareCommand(int $authorId, string $login, int $count): AddFollowersCommand
        {
            $mockUser = new User();
            $userService = Mockery::mock(UserService::class);
            $userService->shouldIgnoreMissing()
                ->shouldReceive('findUserById')
                ->andReturn($mockUser)
                ->once();
            $followerService = Mockery::mock(FollowerService::class);
            $followerService->shouldReceive('addFollowersSync')
                ->withArgs([$mockUser, $login.$authorId, $count])
                ->andReturn($count)
                ->times($count >= 0 ? 1 : 0);
            return new AddFollowersCommand($userService, $followerService);
        }
    
        protected static function executeDataProvider(): array
        {
            return [
                'positive' => [20, 'login', "20 followers were created\n"],
                'zero' => [0, 'other_login', "0 followers were created\n"],
                'default' => [null, 'login3', "10 followers were created\n"],
                'negative' => [-1, 'login_too', "Count should be positive integer\n"],
            ];
        }
    }
    ```
4. Запускаем тесты командой `vendor/bin/simple-phpunit tests/unit/Controller/Cli/AddFollowersCommandTest.php`

## Делаем интерактивный аргумент

1. Исправляем класс `App\Controller\Cli\AddFollowersCommand`
    ```php
    <?php
    
    namespace App\Controller\Cli;
    
    use App\Domain\Service\FollowerService;
    use App\Domain\Service\UserService;
    use Symfony\Component\Console\Attribute\AsCommand;
    use Symfony\Component\Console\Command\Command;
    use Symfony\Component\Console\Input\InputArgument;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Input\InputOption;
    use Symfony\Component\Console\Output\OutputInterface;
    use Symfony\Component\Console\Question\Question;
    
    #[AsCommand(name: self::FOLLOWERS_ADD_COMMAND_NAME, description: 'Add followers to author', hidden: true)]
    final class AddFollowersCommand extends Command
    {
        public const FOLLOWERS_ADD_COMMAND_NAME = 'followers:add';
    
        private const DEFAULT_FOLLOWERS = 10;
        private const DEFAULT_LOGIN_PREFIX = 'Reader #';
    
        public function __construct(
            private readonly UserService $userService,
            private readonly FollowerService $followerService
        ) {
            parent::__construct();
        }
    
        protected function configure(): void
        {
            $this->setName('followers:add')
                ->addArgument('authorId', InputArgument::REQUIRED, 'ID of author')
                ->addOption('login', 'l', InputOption::VALUE_REQUIRED, 'Follower login prefix');
        }
    
        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            $authorId = (int)$input->getArgument('authorId');
            $user = $this->userService->findUserById($authorId);
    
            if ($user === null) {
                $output->write("<error>User with ID $authorId doesn't exist</error>\n");
                return self::FAILURE;
            }
    
            $helper = $this->getHelper('question');
            $question = new Question('How many followers you want to add?', self::DEFAULT_FOLLOWERS);
            $count = (int)$helper->ask($input, $output, $question);
            
            if ($count < 0) {
                $output->write("<error>Count should be positive integer</error>\n");
                return self::FAILURE;
            }
    
            $login = $input->getOption('login') ?? self::DEFAULT_LOGIN_PREFIX;
    
            $result = $this->followerService->addFollowersSync($user, $login.$authorId, $count);
            $output->write("<info>$result followers were created</info>\n");
    
            return self::SUCCESS;
        }
    }
    ```
2. Выполняем команду `php bin/console followers:add 1`, видим дополнительный вопрос по количеству добавляемых фолловеров
3. В классе `UnitTests\Controller\Cli\AddFollowersCommandTest` исправляем метод `testExecuteReturnsResult`
    ```php
    /**
     * @dataProvider executeDataProvider
     */
    public function testExecuteReturnsResult(?int $followersCount, string $login, string $expected): void
    {
        $authorId = 1;
        $command = $this->prepareCommand($authorId, $login, $followersCount ?? self::DEFAULT_FOLLOWERS_COUNT);
        $command->setHelperSet(new HelperSet([new QuestionHelper()]));
        $commandTester = new CommandTester($command);
        $params = ['authorId' => self::TEST_AUTHOR_ID, '--login' => $login];
        $inputs = $followersCount === null ? ["\n"] : ["$followersCount\n"];
        $commandTester->setInputs($inputs);
        $commandTester->execute($params);
        $output = $commandTester->getDisplay();
        static::assertSame($expected, $output);
    }
    ```
4. Запускаем тесты командой `vendor/bin/phpunit tests/unit/Command/AddFollowersCommandTest.php`, видим ошибки
5. В классе `UnitTests\Controller\Cli\AddFollowersCommandTest` исправляем метод `testExecuteReturnsResult`
    ```php
    /**
     * @dataProvider executeDataProvider
     */
    public function testExecuteReturnsResult(?int $followersCount, string $login, string $expected): void
    {
        $authorId = 1;
        $command = $this->prepareCommand($authorId, $login, $followersCount ?? self::DEFAULT_FOLLOWERS_COUNT);
        $command->setHelperSet(new HelperSet([new QuestionHelper()]));
        $commandTester = new CommandTester($command);
        $params = ['authorId' => self::TEST_AUTHOR_ID, '--login' => $login];
        $inputs = $followersCount === null ? ["\n"] : ["$followersCount\n"];
        $commandTester->setInputs($inputs);
        $commandTester->execute($params);
        $output = $commandTester->getDisplay();
        static::assertStringEndsWith($expected, $output);
    }
    ```
6. Запускаем тесты командой `vendor/bin/phpunit tests/unit/Command/AddFollowersCommandTest.php`, видим успешное
   выполнение

## Делаем обработку сигналов

1. Исправляем класс `App\Controller\Cli\AddFollowersCommand`
    ```php
    <?php
    
    namespace App\Controller\Cli;
    
    use App\Domain\Service\FollowerService;
    use App\Domain\Service\UserService;
    use Symfony\Component\Console\Attribute\AsCommand;
    use Symfony\Component\Console\Command\Command;
    use Symfony\Component\Console\Command\SignalableCommandInterface;
    use Symfony\Component\Console\Input\InputArgument;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Input\InputOption;
    use Symfony\Component\Console\Output\OutputInterface;
    use Symfony\Component\Console\Question\Question;
    
    #[AsCommand(name: self::FOLLOWERS_ADD_COMMAND_NAME, description: 'Add followers to author', hidden: true)]
    final class AddFollowersCommand extends Command implements SignalableCommandInterface
    {
        public const FOLLOWERS_ADD_COMMAND_NAME = 'followers:add';
    
        private const DEFAULT_FOLLOWERS = 10;
        private const DEFAULT_LOGIN_PREFIX = 'Reader #';
    
        public function __construct(
            private readonly UserService $userService,
            private readonly FollowerService $followerService
        ) {
            parent::__construct();
        }
    
        protected function configure(): void
        {
            $this->setName('followers:add')
                ->addArgument('authorId', InputArgument::REQUIRED, 'ID of author')
                ->addOption('login', 'l', InputOption::VALUE_REQUIRED, 'Follower login prefix');
        }
    
        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            $authorId = (int)$input->getArgument('authorId');
            $user = $this->userService->findUserById($authorId);
    
            if ($user === null) {
                $output->write("<error>User with ID $authorId doesn't exist</error>\n");
                return self::FAILURE;
            }
    
            $helper = $this->getHelper('question');
            $question = new Question('How many followers you want to add?', self::DEFAULT_FOLLOWERS);
            $count = (int)$helper->ask($input, $output, $question);
    
            if ($count < 0) {
                $output->write("<error>Count should be positive integer</error>\n");
                return self::FAILURE;
            }
    
            $login = $input->getOption('login') ?? self::DEFAULT_LOGIN_PREFIX;
    
            $output->write('<info>Started</info>');
            sleep(100);
            $result = $this->followerService->addFollowersSync($user, $login.$authorId, $count);
            $output->write("<info>$result followers were created</info>\n");
    
            return self::SUCCESS;
        }
    
        public function getSubscribedSignals(): array
        {
            return [SIGINT, SIGTERM];
        }
    
        public function handleSignal(int $signal, false|int $previousExitCode = 0): int|false
        {
            echo $signal;
    
            return false;
        }
    }
    ```
2. Вызываем `php bin/console followers:add 1 -lsignal -n`
3. После появления сообщения `Started` нажимаем Ctrl + C
4. Видим, что программа продолжила выполнение

## Обработка сигналов через EventSubscriber

1. Создаем класс `App\EventSubscriber\AddFollowersSignalSubscriber`
    ```php
    <?php
    
    namespace App\Application\EventSubscriber;
    
    use App\Controller\Cli\AddFollowersCommand;
    use Symfony\Component\Console\ConsoleEvents;
    use Symfony\Component\Console\Event\ConsoleSignalEvent;
    use Symfony\Component\EventDispatcher\EventSubscriberInterface;
    
    class CommandEventSubscriber implements EventSubscriberInterface
    {
        public static function getSubscribedEvents(): array
        {
            return [
                ConsoleEvents::SIGNAL => 'handleSignal',
            ];
        }
    
        public function handleSignal(ConsoleSignalEvent $event): void
        {
            $command = $event->getCommand();
            if ($command !== null && $command->getName() === AddFollowersCommand::FOLLOWERS_ADD_COMMAND_NAME) {
                $signal = $event->getHandlingSignal();
                echo $signal.' via event';
            }
    
            $event->setExitCode(0);
        }
    }
    ```
2. В классе `App\Controller\Cli\AddFollowersCommand` удаляем имплементацию SignalableCommandInterface вместе с
   реализующими её методами
3. Вызываем `php bin/console followers:add 1 -lsignal2 -n`
4. После появления сообщения `Started` нажимаем Ctrl + C
5. Видим новое сообщение, и команда завершается
