<?php

namespace App\Application\Query;

interface QueryBusInterface
{
    /**
     * @template T
     *
     * @param QueryInterface<T> $query
     *
     * @return T
     */
    public function query(QueryInterface $query);
}
