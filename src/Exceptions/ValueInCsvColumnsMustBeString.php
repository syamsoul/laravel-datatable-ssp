<?php

namespace SoulDoit\DataTable\Exceptions;

use InvalidArgumentException;

class ValueInCsvColumnsMustBeString extends InvalidArgumentException
{
    public static function create($givenValue)
    {
        return new static("(CSV) The value in the given column should be string. The value given is not a string (".json_encode($givenValue).")");
    }
}
