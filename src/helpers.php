<?php

if (!function_exists('isNotLumen')) {
    function isNotLumen() : bool
    {
        return ! preg_match('/lumen/i', app()->version());
    }
}

if (!function_exists('getTableNames')) {
    function getTableNames($className)
    {
        $className = '\\'.$className;
        $tbl_prefix = \DB::getTablePrefix();
        $return_tbl = (new $className())->getTable();

        return [
            $return_tbl,
            $tbl_prefix . $return_tbl
        ];
    }
}