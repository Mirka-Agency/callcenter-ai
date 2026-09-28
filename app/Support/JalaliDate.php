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

    public static function isoWeekAxisLabel(string $yearWeek): string
    {
        return self::persianDigits(self::isoWeekNumber($yearWeek));
    }

    public static function isoWeekTooltipLabel(string $yearWeek): string
    {
        $week = self::isoWeekNumber($yearWeek);
        $year = self::persianDigits((int) Jalalian::fromCarbon(self::mondayOfIsoWeek($yearWeek))->format('Y'));

        return $week.'امین هفته '.$year;
    }

    public static function persianDigits(int|string $value): string
    {
        return strtr((string) $value, [
            '0' => '۰',
            '1' => '۱',
            '2' => '۲',
            '3' => '۳',
            '4' => '۴',
            '5' => '۵',
            '6' => '۶',
            '7' => '۷',
            '8' => '۸',
            '9' => '۹',
        ]);
    }

    private static function isoWeekNumber(string $yearWeek): int
    {
        $parts = explode('-', $yearWeek, 2);

        return (int) ($parts[1] ?? $parts[0]);
    }

    private static function mondayOfIsoWeek(string $yearWeek): Carbon
    {
        [$year, $week] = array_pad(explode('-', $yearWeek, 2), 2, '1');

        return Carbon::create((int) $year, 1, 4)->setISODate((int) $year, max(1, (int) $week))->startOfDay();
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
