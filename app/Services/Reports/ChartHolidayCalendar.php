<?php

namespace App\Services\Reports;

use App\Support\ChartDayFilter;
use App\Support\OrganizationHolidays;
use Carbon\CarbonInterface;

class ChartHolidayCalendar
{
    public function __construct(private OrganizationCallMetrics $callMetrics) {}

    public function forRange(int $organizationId, CarbonInterface $from, CarbonInterface $to): ChartDayFilter
    {
        return new ChartDayFilter(
            OrganizationHolidays::weekdays($organizationId),
            $this->callMetrics->extensionActivityDayKeys($organizationId, $from, $to),
        );
    }
}
