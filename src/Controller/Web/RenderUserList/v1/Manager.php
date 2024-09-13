<?php

namespace App\Controller\Web\RenderUserList\v1;

use App\Domain\Entity\EmailUser;
use App\Domain\Entity\PhoneUser;
use App\Domain\Entity\User;
use App\Domain\Service\UserService;
use App\Domain\ValueObject\CommunicationChannelEnum;

class Manager
{
    public function __construct(private readonly UserService $userService) {
    }

    public function getUserListData(): array
    {
        $mapper = static function (User $user): array {
            $result = [
                'id' => $user->getId(),
                'login' => $user->getLogin(),
                'communicationChannel' => null,
                'communicationMethod' => null,
                'roles' => $user->getRoles(),
            ];
            if ($user instanceof PhoneUser) {
                $result['communicationChannel'] = CommunicationChannelEnum::Phone->value;
                $result['communicationMethod'] = $user->getPhone();
            }
            if ($user instanceof EmailUser) {
                $result['communicationChannel'] = CommunicationChannelEnum::Email->value;
                $result['communicationMethod'] = $user->getEmail();
            }

            return $result;
        };

        return array_map($mapper, $this->userService->findAll());
    }
}
