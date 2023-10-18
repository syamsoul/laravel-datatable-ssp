<?php

namespace SoulDoit\DataTable\Exceptions;

use InvalidArgumentException;

class NoPermissionToCallMethod extends InvalidArgumentException
{
    public static function create($className, $methodName)
    {
        return new static("You have no permission to call `" . $className . "::" . $methodName ."` method.");
    }
}
