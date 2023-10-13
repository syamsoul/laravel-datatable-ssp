<?php

namespace SoulDoit\DataTable\Exceptions;

use InvalidArgumentException;

class RawExpressionMustHaveAliasName extends InvalidArgumentException
{
    public static function create(string $givenRawExpression)
    {
        return new static("The given raw expression ($givenRawExpression) should have alias name (Example: $givenRawExpression AS `column_name`).");
    }
}
