<?php

namespace Tests\Unit;

use App\Support\JalaliDate;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class JalaliDateTest extends TestCase
{
    public function test_formats_gregorian_date_as_jalali(): void
    {
        $formatted = JalaliDate::date(Carbon::parse('2024-03-20'));

        $this->assertSame('1403/01/01', $formatted);
    }

    public function test_month_day_keeps_date_only(): void
    {
        $formatted = JalaliDate::monthDay(Carbon::parse('2025-09-16'));

        $this->assertSame('25 شهریور', $formatted);
    }

    public function test_month_day_with_weekday_includes_weekday(): void
    {
        $formatted = JalaliDate::monthDayWithWeekday(Carbon::parse('2025-09-16'));

        $this->assertSame('25 شهریور (سه‌شنبه)', $formatted);
    }

    public function test_converts_jalali_input_to_gregorian(): void
    {
        $gregorian = JalaliDate::toGregorianString('1403/01/01');

        $this->assertSame('2024-03-20', $gregorian);
    }

    public function test_returns_empty_placeholder_for_null(): void
    {
        $this->assertSame('—', JalaliDate::date(null));
        $this->assertSame('—', JalaliDate::datetime(null));
        $this->assertSame('—', shamsi(null, 'datetime'));
    }
}
