<?php

namespace SoulDoit\DataTable\Exceptions;

use InvalidArgumentException;

class MustHaveDbOrDbFake extends InvalidArgumentException
{
    public static function create()
    {
        return new static("Datatable options must have `db` or `db_fake` (e.g `['db' => 'username']` or `['db_fake' => 'fullname']`).");
    }
}
