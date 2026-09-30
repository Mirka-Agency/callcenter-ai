<?php

namespace App\Enums;

use App\Support\CompanyWorkCalendar;
use Carbon\Carbon;

enum ReportDatePreset: string
{
    case Today = 'today';
    case Yesterday = 'yesterday';
    case Last7 = 'last_7';
    case Last30 = 'last_30';
    case ThisMonth = 'this_month';
    case PreviousMonth = 'previous_month';
    case CurrentQuarter = 'current_quarter';
    case CurrentYear = 'current_year';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Today => 'امروز',
            self::Yesterday => 'دیروز',
            self::Last7 => '۷ روز گذشته',
            self::Last30 => '۳۰ روز گذشته',
            self::ThisMonth => 'این ماه',
            self::PreviousMonth => 'ماه قبل',
            self::CurrentQuarter => 'فصل جاری',
            self::CurrentYear => 'سال جاری',
            self::Custom => 'بازه دلخواه',
        };
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public function resolve(?Carbon $customFrom = null, ?Carbon $customTo = null): array
    {
        return match ($this) {
            self::Today => self::tehranDayRange(0),
            self::Yesterday => self::tehranDayRange(1),
            self::Last7 => [
                self::tehranMoment()->subDays(6)->startOfDay()->utc(),
                self::tehranMoment()->endOfDay()->utc(),
            ],
            self::Last30 => [
                self::tehranMoment()->subDays(29)->startOfDay()->utc(),
                self::tehranMoment()->endOfDay()->utc(),
            ],
            self::ThisMonth => [
                self::tehranMoment()->startOfMonth()->startOfDay()->utc(),
                self::tehranMoment()->endOfDay()->utc(),
            ],
            self::PreviousMonth => [
                self::tehranMoment()->subMonthNoOverflow()->startOfMonth()->startOfDay()->utc(),
                self::tehranMoment()->subMonthNoOverflow()->endOfMonth()->endOfDay()->utc(),
            ],
            self::CurrentQuarter => [
                self::tehranMoment()->startOfQuarter()->startOfDay()->utc(),
                self::tehranMoment()->endOfDay()->utc(),
            ],
            self::CurrentYear => [
                self::tehranMoment()->startOfYear()->startOfDay()->utc(),
                self::tehranMoment()->endOfDay()->utc(),
            ],
            self::Custom => [
                ($customFrom ?? self::tehranMoment()->subDays(29))->copy()
                    ->timezone(CompanyWorkCalendar::TIMEZONE)
                    ->startOfDay()
                    ->utc(),
                ($customTo ?? self::tehranMoment())->copy()
                    ->timezone(CompanyWorkCalendar::TIMEZONE)
                    ->endOfDay()
                    ->utc(),
            ],
        };
    }

    /** @return list<self> */
    public static function selectable(): array
    {
        return [
            self::Today,
            self::Yesterday,
            self::Last7,
            self::Last30,
            self::PreviousMonth,
            self::CurrentQuarter,
            self::CurrentYear,
            self::Custom,
        ];
    }

    /** @return list<self> */
    public static function namedPresets(): array
    {
        return array_values(array_filter(
            self::selectable(),
            fn (self $preset) => $preset !== self::Custom,
        ));
    }

    private static function tehranMoment(): Carbon
    {
        return now(CompanyWorkCalendar::TIMEZONE);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private static function tehranDayRange(int $daysAgo): array
    {
        $day = self::tehranMoment()->subDays($daysAgo);

        return [
            $day->copy()->startOfDay()->utc(),
            $day->copy()->endOfDay()->utc(),
        ];
    }
}
