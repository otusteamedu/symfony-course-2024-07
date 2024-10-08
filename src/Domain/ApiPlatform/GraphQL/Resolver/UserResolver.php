<?php

namespace App\Domain\ApiPlatform\GraphQL\Resolver;

use ApiPlatform\GraphQl\Resolver\QueryItemResolverInterface;
use App\Domain\Entity\User;
use App\Domain\Service\UserService;

class UserResolver implements QueryItemResolverInterface
{
    private const MASK = '****';

    public function __construct(private readonly UserService $userService) {
    }

    /**
     * @param User|null $item
     */
    public function __invoke($item, array $context): User
    {
        if (isset($context['args']['_id'])) {
            $item = $this->userService->findUserById($context['args']['_id']);
        } elseif (isset($context['args']['login'])) {
            $item = $this->userService->findUserByLogin($context['args']['login']);
        }

        if ($item->isProtected()) {
            $item->setLogin(self::MASK);
            $item->setPassword(self::MASK);
        }

        return $item;
    }
}
