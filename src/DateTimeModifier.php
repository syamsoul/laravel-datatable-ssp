<?php
namespace SoulDoit\DataTable;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Carbon;

class DateTimeModifier
{
    private static $is_constructed = false;
    private static $timezone;

    public static function construct()
    {
        if (!self::$is_constructed) {
            self::$timezone = config('sd-datatable-ssp.default_modifier_timezone', 'UTC');
            self::$is_constructed = true;
        }
    }

    public static function setTimezone(string $timezone)
    {
        self::$timezone = $timezone;
    }

    public static function getTimezone() : string
    {
        return self::$timezone;
    }

    public static function getDateTimeCarbon(string|Carbon|null $datetime = null): ?Carbon
    {
        if ($datetime === null) return now(self::$timezone);

        if ($datetime instanceof Carbon) return $datetime->copy()->tz(self::$timezone);
        else return Carbon::parse($datetime)->copy()->tz(self::$timezone);
        
        return null;
    }
    
    public static function getMysqlQueryTzRaw(string $db_column_name, string $timezone_from = null): string
    {
        if ($timezone_from) $from_datetime_carbon = now($timezone_from);
        else $from_datetime_carbon = now();

        return "CONVERT_TZ($db_column_name, '".$from_datetime_carbon->format("P")."', '".now(self::$timezone)->format("P")."')";
    }

    public static function getMysqlQueryTzRawDB(string $db_column_name, string $timezone_from = null): Expression
    {
        return DB::raw(self::getMysqlQueryTzRaw($db_column_name, $timezone_from));
    }
}

DateTimeModifier::construct();