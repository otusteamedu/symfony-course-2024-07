<?php

namespace App\Domain\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Controller\Web\CreateUser\v2\Input\CreateUserDTO;
use App\Controller\Web\CreateUser\v2\Manager;
use App\Controller\Web\CreateUser\v2\Output\CreatedUserDTO;

/**
 * @implements ProcessorInterface<CreateUserDTO, CreatedUserDTO|void>
 */
class UserProcessor implements ProcessorInterface
{
    public function __construct(private readonly Manager $manager)
    {
    }

    /**
     * @param CreateUserDTO $data
     * @return CreatedUserDTO|void
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        return $this->manager->create($data);
    }
}
