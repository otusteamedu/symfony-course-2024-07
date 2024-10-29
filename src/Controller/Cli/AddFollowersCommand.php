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
