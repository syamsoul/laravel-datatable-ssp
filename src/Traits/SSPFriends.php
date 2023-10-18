<?php

namespace SoulDoit\DataTable\Traits;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use SoulDoit\DataTable\Handler\ColumnsHandler;
use SoulDoit\DataTable\Handler\DataHandler;
use SoulDoit\DataTable\Exceptions\NoPermissionToCallMethod;

trait SSPFriends
{
    private $__friends = [
        ColumnsHandler::class,
        DataHandler::class,
    ];

    public function callColumns(): ?array
    {
        $this->checkFriends();
        return $this->columns();
    }

    public function callQuery(array $selected_columns): EloquentBuilder|QueryBuilder
    {
        $this->checkFriends();
        return $this->query($selected_columns);
    }

    public function callQueryCustomFilter(EloquentBuilder|QueryBuilder $query): void
    {
        $this->checkFriends();
        $this->queryCustomFilter($query);
    }

    public function callQueryCount(EloquentBuilder|QueryBuilder $query): int
    {
        $this->checkFriends();
        return $this->queryCount($query);
    }

    private function checkFriends()
    {
        $trace = debug_backtrace();

        if (isset($trace[2]['class']) && !in_array($trace[2]['class'], $this->__friends)) {
            throw NoPermissionToCallMethod::create($trace[1]['class'], $trace[1]['function']);
        }
    }
}