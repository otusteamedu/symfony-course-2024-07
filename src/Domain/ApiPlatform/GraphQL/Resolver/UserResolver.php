<?php

namespace App\Domain\ApiPlatform\GraphQL\Resolver;

use ApiPlatform\GraphQl\Resolver\QueryItemResolverInterface;
use App\Domain\Entity\User;

class UserResolver implements QueryItemResolverInterface
{
    private const MASK = '****';

    /**
     * @param User|null $item
     */
    public function __invoke($item, array $context): User
    {
        if ($item->isProtected()) {
            $item->setLogin(self::MASK);
            $item->setPassword(self::MASK);
        }

        return $item;
    }
}
