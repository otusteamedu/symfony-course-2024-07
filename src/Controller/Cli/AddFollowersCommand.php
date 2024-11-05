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

        $output->write('<info>Started</info>');
        $result = $this->followerService->addFollowersSync($user, $login.$authorId, $count);
        $output->write("<info>$result followers were created</info>\n");

        return self::SUCCESS;
    }
}
