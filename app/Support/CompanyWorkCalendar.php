<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Iranian company week: Saturday–Wednesday are working days.
 * Thursday and Friday are company holidays and stay off charts and analytics.
 */
class CompanyWorkCalendar
{
    public const TIMEZONE = 'Asia/Tehran';

    public static function dayKey(CarbonInterface $moment): string
    {
        $carbon = $moment instanceof Carbon ? $moment->copy() : Carbon::instance($moment);

        return $carbon->utc()->timezone(self::TIMEZONE)->toDateString();
    }

    public static function isHoliday(string $dayKey): bool
    {
        $day = self::parseDayKey($dayKey);

        return $day !== null && ($day->isThursday() || $day->isFriday());
    }

    public static function isHolidayMoment(CarbonInterface $moment): bool
    {
        return self::isHoliday(self::dayKey($moment));
    }

    public static function isFriday(string $dayKey): bool
    {
        $day = self::parseDayKey($dayKey);

        return $day !== null && $day->isFriday();
    }

    /**
     * Keep rows whose timestamp falls on Saturday–Wednesday in Tehran.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function whereWorkday(Builder $query, string $utcTimestampSql): Builder
    {
        $driver = $query->getConnection()->getDriverName();

        $dow = match ($driver) {
            'pgsql' => "EXTRACT(DOW FROM (($utcTimestampSql) AT TIME ZONE 'UTC') AT TIME ZONE '".self::TIMEZONE."')",
            'mysql', 'mariadb' => "DAYOFWEEK(CONVERT_TZ($utcTimestampSql, '+00:00', '+03:30'))",
            default => "CAST(strftime('%w', datetime($utcTimestampSql, '+210 minutes')) AS INTEGER)",
        };

        $holidays = in_array($driver, ['mysql', 'mariadb'], true) ? '(5, 6)' : '(4, 5)';

        return $query->whereRaw("($dow) NOT IN $holidays");
    }

    private static function parseDayKey(string $dayKey): ?Carbon
    {
        if ($dayKey === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dayKey)) {
            return null;
        }

        $day = Carbon::createFromFormat('!Y-m-d', $dayKey, 'UTC');

        return $day === false ? null : $day;
    }
}
