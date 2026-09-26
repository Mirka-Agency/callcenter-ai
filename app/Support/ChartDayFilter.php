<?php

namespace App\Support;

/**
 * Daily chart points to skip: the company's weekday holidays, plus past days
 * when no registered extension received a call.
 */
final class ChartDayFilter
{
    /**
     * @param  list<int>  $holidayWeekdays
     * @param  array<string, true>|null  $activeExtensionDays
     */
    public function __construct(
        private readonly array $holidayWeekdays,
        private readonly ?array $activeExtensionDays,
    ) {}

    public function hides(string $dayKey): bool
    {
        if (CompanyWorkCalendar::isHoliday($dayKey, $this->holidayWeekdays)) {
            return true;
        }

        if ($this->activeExtensionDays === null || ! $this->isDayKey($dayKey)) {
            return false;
        }

        return ! isset($this->activeExtensionDays[$dayKey]);
    }

    private function isDayKey(string $dayKey): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $dayKey) === 1;
    }
}
