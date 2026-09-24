<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Iranian company week: Saturday–Thursday are working days. Only Friday is off.
 */
class CompanyWorkCalendar
{
    public const TIMEZONE = 'Asia/Tehran';

    /**
     * Calendar day used by charts.
     *
     * Thursday must stay Thursday. A Tehran offset must not fold a Thursday
     * timestamp into Friday and drop it as a holiday.
     */
    public static function dayKey(CarbonInterface $moment): string
    {
        $utc = $moment instanceof Carbon ? $moment->copy()->utc() : Carbon::instance($moment)->utc();
        $tehran = $utc->copy()->timezone(self::TIMEZONE);

        if ($tehran->isThursday()) {
            return $tehran->toDateString();
        }

        if ($utc->isThursday()) {
            return $utc->toDateString();
        }

        return $tehran->toDateString();
    }

    public static function isFriday(string $dayKey): bool
    {
        if ($dayKey === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dayKey)) {
            return false;
        }

        $day = Carbon::createFromFormat('!Y-m-d', $dayKey, 'UTC');

        return $day !== false && $day->isFriday();
    }
}
