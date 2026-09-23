<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Morilog\Jalali\Jalalian;

class JalaliDate
{
    public const DATE = 'Y/m/d';

    public const DATETIME = 'Y/m/d H:i';

    public const DATETIME_SECONDS = 'Y/m/d H:i:s';

    public const TIME = 'H:i';

    public const MONTH_DAY = 'j F';

    public const MONTH_DAY_WEEKDAY = 'j F (l)';

    public static function format(
        DateTimeInterface|string|int|null $value,
        string $format = self::DATE,
        ?string $empty = '—',
    ): string {
        if ($value === null || $value === '') {
            return $empty ?? '—';
        }

        try {
            $carbon = $value instanceof CarbonInterface
                ? $value
                : Carbon::parse($value);
        } catch (\Throwable) {
            return $empty ?? '—';
        }

        return Jalalian::fromCarbon($carbon)->format($format);
    }

    public static function date(DateTimeInterface|string|int|null $value, ?string $empty = '—'): string
    {
        return self::format($value, self::DATE, $empty);
    }

    public static function datetime(DateTimeInterface|string|int|null $value, ?string $empty = '—'): string
    {
        return self::format($value, self::DATETIME, $empty);
    }

    public static function datetimeSeconds(DateTimeInterface|string|int|null $value, ?string $empty = '—'): string
    {
        return self::format($value, self::DATETIME_SECONDS, $empty);
    }

    public static function time(DateTimeInterface|string|int|null $value, ?string $empty = '—'): string
    {
        if ($value === null || $value === '') {
            return $empty ?? '—';
        }

        try {
            $carbon = $value instanceof CarbonInterface
                ? $value
                : Carbon::parse($value);
        } catch (\Throwable) {
            return $empty ?? '—';
        }

        return $carbon->format(self::TIME);
    }

    public static function monthDay(DateTimeInterface|string|int|null $value, ?string $empty = '—'): string
    {
        return self::format($value, self::MONTH_DAY, $empty);
    }

    public static function monthDayWithWeekday(DateTimeInterface|string|int|null $value, ?string $empty = '—'): string
    {
        return self::format($value, self::MONTH_DAY_WEEKDAY, $empty);
    }

    public static function ago(DateTimeInterface|string|int|null $value, ?string $empty = '—'): string
    {
        if ($value === null || $value === '') {
            return $empty ?? '—';
        }

        try {
            $carbon = $value instanceof CarbonInterface
                ? $value
                : Carbon::parse($value);
        } catch (\Throwable) {
            return $empty ?? '—';
        }

        return Jalalian::fromCarbon($carbon)->ago();
    }

    public static function range(
        DateTimeInterface|string|int|null $from,
        DateTimeInterface|string|int|null $to,
        string $separator = ' — ',
    ): string {
        return self::date($from).' '.$separator.' '.self::date($to);
    }

    public static function toGregorian(?string $jalali, string $format = 'Y-m-d'): ?Carbon
    {
        if ($jalali === null || trim($jalali) === '') {
            return null;
        }

        $normalized = str_replace('-', '/', trim($jalali));

        try {
            return Jalalian::fromFormat($format === 'Y-m-d' ? 'Y/m/d' : $format, $normalized)->toCarbon();
        } catch (\Throwable) {
            try {
                return Jalalian::fromFormat('Y/m/d', $normalized)->toCarbon();
            } catch (\Throwable) {
                return null;
            }
        }
    }

    public static function toGregorianString(?string $jalali, string $format = 'Y-m-d'): ?string
    {
        return self::toGregorian($jalali)?->format($format);
    }
}
