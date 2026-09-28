<?php

namespace App\Support;

use App\Models\Organization;

class OrganizationHolidays
{
    /**
     * Weekdays this company treats as holidays.
     * Carbon day numbers: Sunday = 0 … Saturday = 6.
     * A missing setting keeps Thursday and Friday, the historical default.
     *
     * @return list<int>
     */
    public static function weekdays(int $organizationId): array
    {
        $key = 'organization.holiday_weekdays.'.$organizationId;

        if (app()->bound($key)) {
            return app($key);
        }

        $organization = Organization::query()->find($organizationId);
        $weekdays = $organization === null
            ? CompanyWorkCalendar::DEFAULT_HOLIDAY_WEEKDAYS
            : $organization->holidayWeekdays();

        app()->instance($key, $weekdays);

        return $weekdays;
    }

    public static function cacheToken(int $organizationId): string
    {
        $weekdays = self::weekdays($organizationId);

        return 'holidays:'.($weekdays === [] ? 'none' : implode('-', $weekdays));
    }
}
