<?php

namespace App\Controller\Web\CreateUser\v1;

use App\Controller\Web\CreateUser\v1\Input\CreateUserDTO;
use App\Domain\Entity\User;
use App\Domain\Model\CreateUserModel;
use App\Domain\Service\UserService;
use App\Domain\ValueObject\CommunicationChannelEnum;

class Manager
{
    public function __construct(
        private readonly UserService $userService,
    ) {
    }

    public function create(CreateUserDTO $createUserDTO): User
    {
        $communicationMethod = $createUserDTO->phone ?? $createUserDTO->email;
        $communicationChannel = $createUserDTO->phone === null ? CommunicationChannelEnum::Email : CommunicationChannelEnum::Phone;
        $createUserModel = new CreateUserModel($createUserDTO->login, $communicationMethod, $communicationChannel);

        return $this->userService->create($createUserModel);
    }
}
