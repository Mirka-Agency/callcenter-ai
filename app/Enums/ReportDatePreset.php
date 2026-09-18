<?php

namespace App\Enums;

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
            self::Today => [now()->startOfDay(), now()->endOfDay()],
            self::Yesterday => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            self::Last7 => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
            self::Last30 => [now()->subDays(29)->startOfDay(), now()->endOfDay()],
            self::ThisMonth => [now()->startOfMonth(), now()->endOfDay()],
            self::PreviousMonth => [
                now()->subMonth()->startOfMonth(),
                now()->subMonth()->endOfMonth(),
            ],
            self::CurrentQuarter => [
                now()->startOfQuarter(),
                now()->endOfDay(),
            ],
            self::CurrentYear => [
                now()->startOfYear(),
                now()->endOfDay(),
            ],
            self::Custom => [
                ($customFrom ?? now()->subDays(29))->copy()->startOfDay(),
                ($customTo ?? now())->copy()->endOfDay(),
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
            self::ThisMonth,
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
}
