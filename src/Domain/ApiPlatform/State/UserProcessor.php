<?php

namespace App\Domain\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Entity\User;
use App\Infrastructure\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * @implements ProcessorInterface<User, User|void>
 */
class UserProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * @param User $data
     * @return User|void
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $data->setPassword($this->userPasswordHasher->hashPassword($data, $data->getPassword()));
        $this->userRepository->create($data);

        return $data;
    }
}
