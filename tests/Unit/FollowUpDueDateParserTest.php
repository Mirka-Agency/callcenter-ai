<?php

namespace Tests\Unit;

use App\Support\FollowUpDueDateParser;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class FollowUpDueDateParserTest extends TestCase
{
    public function test_parses_relative_persian_follow_up_dates(): void
    {
        $from = Carbon::parse('2026-09-10 14:30:00');

        $this->assertSame('2026-09-11', FollowUpDueDateParser::parse('تماس پیگیری فردا', $from)?->toDateString());
        $this->assertSame('2026-09-11', FollowUpDueDateParser::parse('تماس پیگیری در روز بعد', $from)?->toDateString());
        $this->assertSame('2026-09-12', FollowUpDueDateParser::parse('پیگیری پس‌فردا', $from)?->toDateString());
        $this->assertSame('2026-09-10', FollowUpDueDateParser::parse('ارسال پیش‌فاکتور در همین روز', $from)?->toDateString());
        $this->assertSame('2026-09-13', FollowUpDueDateParser::parse('پیگیری ۳ روز دیگر', $from)?->toDateString());
        $this->assertSame('2026-09-17', FollowUpDueDateParser::parse('پیگیری هفته آینده', $from)?->toDateString());
    }

    public function test_parses_weekday_and_explicit_dates(): void
    {
        $thursday = Carbon::parse('2026-09-10');

        $this->assertSame('2026-09-10', FollowUpDueDateParser::parse('تماس پیگیری پنج‌شنبه', $thursday)?->toDateString());
        $this->assertSame('2026-09-12', FollowUpDueDateParser::parse('تماس پیگیری شنبه', $thursday)?->toDateString());
        $this->assertSame('2026-09-12', FollowUpDueDateParser::parse('پیگیری ۱۴۰۵/۰۶/۲۱', $thursday)?->toDateString());
    }

    public function test_defaults_to_the_next_day_when_no_date_is_mentioned(): void
    {
        $from = Carbon::parse('2026-09-10 09:00:00');

        $this->assertSame('2026-09-11', FollowUpDueDateParser::parseOrDefault('ارسال پیش‌فاکتور', $from)->toDateString());
        $this->assertNull(FollowUpDueDateParser::parse('ارسال پیش‌فاکتور', $from));
    }
}
