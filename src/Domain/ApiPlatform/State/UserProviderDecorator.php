<?php

namespace App\Domain\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Controller\Web\CreateUser\v2\Output\CreatedUserDTO;
use App\Domain\Entity\EmailUser;
use App\Domain\Entity\PhoneUser;
use App\Domain\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProviderInterface<CreatedUserDTO>
 */
class UserProviderDecorator implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
        private readonly ProviderInterface $itemProvider,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /** @var User $user */
        $user = $this->itemProvider->provide($operation, $uriVariables, $context);

        return new CreatedUserDTO(
            $user->getId(),
            $user->getLogin(),
            $user->getAvatarLink(),
            $user->getRoles(),
            $user->getCreatedAt()->format('Y-m-d H:i:s'),
            $user->getUpdatedAt()->format('Y-m-d H:i:s'),
            $user instanceof PhoneUser ? $user->getPhone() : null,
            $user instanceof EmailUser ? $user->getEmail() : null,
        );
    }
}
