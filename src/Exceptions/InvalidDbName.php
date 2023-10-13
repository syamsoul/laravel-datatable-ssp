<?php

namespace SoulDoit\DataTable\Exceptions;

use InvalidArgumentException;

class InvalidDbName extends InvalidArgumentException
{
    public static function create(string $db)
    {
        return new static("The given DB Name ($db) is invalid.");
    }
}
