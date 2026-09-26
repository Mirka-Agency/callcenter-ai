<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Iranian company week in Asia/Tehran.
 * Which weekdays are holidays is chosen per organization.
 * When a company has not chosen yet, Thursday and Friday stay off charts and analytics.
 */
class CompanyWorkCalendar
{
    public const TIMEZONE = 'Asia/Tehran';

    /** @var list<int> */
    public const DEFAULT_HOLIDAY_WEEKDAYS = [
        Carbon::THURSDAY,
        Carbon::FRIDAY,
    ];

    public static function dayKey(CarbonInterface $moment): string
    {
        $carbon = $moment instanceof Carbon ? $moment->copy() : Carbon::instance($moment);

        return $carbon->utc()->timezone(self::TIMEZONE)->toDateString();
    }

    /**
     * @param  list<int>|null  $holidayWeekdays  null keeps Thursday and Friday
     */
    public static function isHoliday(string $dayKey, ?array $holidayWeekdays = null): bool
    {
        $day = self::parseDayKey($dayKey);

        return $day !== null && in_array($day->dayOfWeek, self::normalizeWeekdays($holidayWeekdays), true);
    }

    /**
     * @param  list<int>|null  $holidayWeekdays  null keeps Thursday and Friday
     */
    public static function isHolidayMoment(CarbonInterface $moment, ?array $holidayWeekdays = null): bool
    {
        return self::isHoliday(self::dayKey($moment), $holidayWeekdays);
    }

    public static function isFriday(string $dayKey): bool
    {
        $day = self::parseDayKey($dayKey);

        return $day !== null && $day->isFriday();
    }

    /**
     * Saturday through Friday, in the Iranian week order.
     *
     * @return array<int, string>
     */
    public static function weekdayOptions(): array
    {
        return [
            Carbon::SATURDAY => 'شنبه',
            Carbon::SUNDAY => 'یکشنبه',
            Carbon::MONDAY => 'دوشنبه',
            Carbon::TUESDAY => 'سه‌شنبه',
            Carbon::WEDNESDAY => 'چهارشنبه',
            Carbon::THURSDAY => 'پنجشنبه',
            Carbon::FRIDAY => 'جمعه',
        ];
    }

    public static function weekdayLabel(int $weekday): string
    {
        return self::weekdayOptions()[$weekday] ?? '';
    }

    /**
     * Keep rows whose timestamp falls on a working day in Tehran.
     *
     * @param  Builder<Model>  $query
     * @param  list<int>|null  $holidayWeekdays  null keeps Thursday and Friday
     * @return Builder<Model>
     */
    public static function whereWorkday(Builder $query, string $utcTimestampSql, ?array $holidayWeekdays = null): Builder
    {
        $weekdays = self::normalizeWeekdays($holidayWeekdays);

        if ($weekdays === []) {
            return $query;
        }

        $driver = $query->getConnection()->getDriverName();

        $dow = match ($driver) {
            'pgsql' => "EXTRACT(DOW FROM (($utcTimestampSql) AT TIME ZONE 'UTC') AT TIME ZONE '".self::TIMEZONE."')",
            'mysql', 'mariadb' => "DAYOFWEEK(CONVERT_TZ($utcTimestampSql, '+00:00', '+03:30'))",
            default => "CAST(strftime('%w', datetime($utcTimestampSql, '+210 minutes')) AS INTEGER)",
        };

        $values = array_map(
            fn (int $weekday): int => in_array($driver, ['mysql', 'mariadb'], true) ? $weekday + 1 : $weekday,
            $weekdays,
        );

        $holidays = '('.implode(', ', $values).')';

        return $query->whereRaw("($dow) NOT IN $holidays");
    }

    /**
     * @return list<int>
     */
    public static function normalizeWeekdays(mixed $holidayWeekdays): array
    {
        if (! is_array($holidayWeekdays)) {
            return self::DEFAULT_HOLIDAY_WEEKDAYS;
        }

        $days = [];

        foreach ($holidayWeekdays as $day) {
            if (! is_numeric($day)) {
                continue;
            }

            $weekday = (int) $day;

            if ($weekday >= Carbon::SUNDAY && $weekday <= Carbon::SATURDAY) {
                $days[$weekday] = $weekday;
            }
        }

        ksort($days);

        return array_values($days);
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
