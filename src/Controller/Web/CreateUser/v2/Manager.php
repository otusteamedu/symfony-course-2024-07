<?php

namespace App\Controller\Web\CreateUser\v2;

use App\Controller\Web\CreateUser\v2\Input\CreateUserDTO;
use App\Controller\Web\CreateUser\v2\Output\CreatedUserDTO;
use App\Domain\Command\CreateUser\Command;
use App\Domain\Entity\EmailUser;
use App\Domain\Entity\PhoneUser;
use App\Domain\Model\CreateUserModel;
use App\Domain\Service\ModelFactory;
use App\Domain\Service\UserService;
use App\Domain\ValueObject\CommunicationChannelEnum;
use Symfony\Component\Messenger\MessageBusInterface;

class Manager implements ManagerInterface
{
    private const MAX_RETRIES_COUNT = 10;
    private const WAIT_INTERVAL_MICROSECONDS = 1_000_000;

    public function __construct(
        /** @var ModelFactory<CreateUserModel> */
        private readonly ModelFactory $modelFactory,
        private readonly UserService $userService,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function create(CreateUserDTO $createUserDTO): CreatedUserDTO
    {
        $communicationMethod = $createUserDTO->phone ?? $createUserDTO->email;
        $communicationChannel = $createUserDTO->phone === null ? CommunicationChannelEnum::Email : CommunicationChannelEnum::Phone;
        $createUserModel = $this->modelFactory->makeModel(
            CreateUserModel::class,
            $createUserDTO->login,
            $communicationMethod,
            $communicationChannel,
            $createUserDTO->password,
            $createUserDTO->age,
            $createUserDTO->isActive,
            $createUserDTO->roles
        );
        $this->messageBus->dispatch(new Command($createUserModel));

        $retriesCount = 0;
        $users = [];
        while ($users === [] && $retriesCount < self::MAX_RETRIES_COUNT) {
            usleep(self::WAIT_INTERVAL_MICROSECONDS);
            $users = $this->userService->findUsersByLogin($createUserDTO->login);
            $retriesCount++;
        }
        $user = $users[0];

        return new CreatedUserDTO(
            $user->getId(),
            $user->getLogin()->getValue(),
            $user->getAvatarLink(),
            $user->getRoles(),
            $user->getCreatedAt()->format('Y-m-d H:i:s'),
            $user->getUpdatedAt()->format('Y-m-d H:i:s'),
            $user instanceof PhoneUser ? $user->getPhone() : null,
            $user instanceof EmailUser ? $user->getEmail() : null,
        );
    }
}
