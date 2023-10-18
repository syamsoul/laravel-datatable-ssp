<?php

namespace SoulDoit\DataTable\Exceptions;

use InvalidArgumentException;

class MustHaveQuery extends InvalidArgumentException
{
    public static function create()
    {
        return new static("Query must be setted via `setQuery` method or by override `query` method.");
    }
}
