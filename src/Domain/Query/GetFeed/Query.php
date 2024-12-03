<?php

namespace App\Domain\Query\GetFeed;

use App\Application\Query\QueryInterface;

/**
 * @implements QueryInterface<Result>
 */
class Query implements QueryInterface
{
    public function __construct(
        public readonly int $userId,
        public readonly int $count,
    ) {
    }
}
