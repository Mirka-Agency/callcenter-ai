<?php

namespace Tests\Unit;

use App\Support\CompanyWorkCalendar;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class CompanyWorkCalendarTest extends TestCase
{
    public function test_only_friday_is_a_holiday_and_thursday_stays_a_workday(): void
    {
        $thursday = Carbon::parse('2026-09-17 11:00:00', CompanyWorkCalendar::TIMEZONE);
        $friday = $thursday->copy()->addDay()->setTime(11, 0);

        $this->assertSame('2026-09-17', CompanyWorkCalendar::dayKey($thursday));
        $this->assertFalse(CompanyWorkCalendar::isFriday('2026-09-17'));

        $this->assertSame('2026-09-18', CompanyWorkCalendar::dayKey($friday));
        $this->assertTrue(CompanyWorkCalendar::isFriday('2026-09-18'));
    }

    public function test_thursday_evening_stored_as_utc_is_not_folded_into_friday(): void
    {
        $storedAsUtc = Carbon::parse('2026-09-17 22:30:00', 'UTC');

        $this->assertTrue($storedAsUtc->copy()->timezone(CompanyWorkCalendar::TIMEZONE)->isFriday());
        $this->assertSame('2026-09-17', CompanyWorkCalendar::dayKey($storedAsUtc));
        $this->assertFalse(CompanyWorkCalendar::isFriday('2026-09-17'));
    }

    public function test_early_thursday_in_tehran_stays_thursday(): void
    {
        $earlyThursday = Carbon::parse('2026-09-17 00:40:00', CompanyWorkCalendar::TIMEZONE);

        $this->assertTrue($earlyThursday->copy()->utc()->isWednesday());
        $this->assertSame('2026-09-17', CompanyWorkCalendar::dayKey($earlyThursday));
    }
}
