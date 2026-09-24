<?php

namespace Tests\Unit;

use App\Support\CompanyWorkCalendar;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class CompanyWorkCalendarTest extends TestCase
{
    public function test_thursday_and_friday_are_holidays_and_wednesday_is_a_workday(): void
    {
        $thursday = Carbon::parse('2026-09-17 11:00:00', CompanyWorkCalendar::TIMEZONE);
        $friday = $thursday->copy()->addDay()->setTime(11, 0);
        $wednesday = $thursday->copy()->subDay()->setTime(11, 0);

        $this->assertSame('2026-09-17', CompanyWorkCalendar::dayKey($thursday));
        $this->assertTrue(CompanyWorkCalendar::isHoliday('2026-09-17'));
        $this->assertFalse(CompanyWorkCalendar::isFriday('2026-09-17'));

        $this->assertSame('2026-09-18', CompanyWorkCalendar::dayKey($friday));
        $this->assertTrue(CompanyWorkCalendar::isHoliday('2026-09-18'));
        $this->assertTrue(CompanyWorkCalendar::isFriday('2026-09-18'));

        $this->assertSame('2026-09-16', CompanyWorkCalendar::dayKey($wednesday));
        $this->assertFalse(CompanyWorkCalendar::isHoliday('2026-09-16'));
    }

    public function test_tehran_friday_morning_stays_friday_when_the_utc_clock_is_still_thursday(): void
    {
        $storedAsUtc = Carbon::parse('2026-09-17 22:30:00', 'UTC');

        $this->assertTrue($storedAsUtc->copy()->timezone(CompanyWorkCalendar::TIMEZONE)->isFriday());
        $this->assertSame('2026-09-18', CompanyWorkCalendar::dayKey($storedAsUtc));
        $this->assertTrue(CompanyWorkCalendar::isHoliday('2026-09-18'));
    }

    public function test_early_thursday_in_tehran_stays_thursday(): void
    {
        $earlyThursday = Carbon::parse('2026-09-17 00:40:00', CompanyWorkCalendar::TIMEZONE);

        $this->assertTrue($earlyThursday->copy()->utc()->isWednesday());
        $this->assertSame('2026-09-17', CompanyWorkCalendar::dayKey($earlyThursday));
        $this->assertTrue(CompanyWorkCalendar::isHolidayMoment($earlyThursday));
    }
}
