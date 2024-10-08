<?php

namespace App\Controller\Web\CreateUser\v2;

use App\Controller\Web\CreateUser\v2\Input\CreateUserDTO;
use App\Controller\Web\CreateUser\v2\Output\CreatedUserDTO;
use Psr\Log\LoggerInterface;

class ManagerLoggerDecorator implements ManagerInterface
{
    public function __construct(
        private readonly ManagerInterface $manager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function create(CreateUserDTO $createUserDTO): CreatedUserDTO
    {
        $this->addLogs();

        return $this->manager->create($createUserDTO);
    }

    private function addLogs(): void
    {
        $this->logger->debug('This is debug message');
        $this->logger->info('This is info message');
        $this->logger->notice('This is notice message');
        $this->logger->warning('This is warning message');
        $this->logger->error('This is error message');
        $this->logger->critical('This is critical message');
        $this->logger->alert('This is alert message');
        $this->logger->emergency('This is emergency message');
    }
}
