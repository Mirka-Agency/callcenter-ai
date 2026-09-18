<?php

namespace Tests\Unit;

use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Models\ConversationAnalysis;
use App\Support\CustomerNextActionAggregator;
use Carbon\Carbon;
use Tests\TestCase;

class CustomerNextActionAggregatorTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_returns_at_most_five_unique_actions(): void
    {
        $analysis = $this->analysis([
            'next_actions_json' => [
                'ارسال کاتالوگ',
                'هماهنگی جلسه معرفی',
                'پیگیری هفته آینده',
                'ارسال لینک آموزش',
                'یادآوری تمدید',
                'ارسال نمونه قرارداد',
                'ثبت یادداشت در سی‌آر‌ام',
            ],
        ]);

        $actions = CustomerNextActionAggregator::prioritized([$analysis]);

        $this->assertCount(5, $actions);
        $this->assertSame([
            'ارسال کاتالوگ',
            'هماهنگی جلسه معرفی',
            'پیگیری هفته آینده',
            'ارسال لینک آموزش',
            'یادآوری تمدید',
        ], $actions);
    }

    public function test_it_prefers_critical_and_urgent_actions_over_routine_ones(): void
    {
        Carbon::setTestNow('2026-09-18 10:00:00');

        $routine = $this->analysis([
            'analyzed_at' => now(),
            'next_actions_json' => [
                'ارسال کاتالوگ محصول',
                'هماهنگی جلسه معرفی',
                'ارسال لینک آموزش',
            ],
        ]);

        $critical = $this->analysis([
            'analyzed_at' => now()->subDay(),
            'needs_attention' => true,
            'sentiment' => AnalysisSentiment::Negative,
            'customer_insights_json' => [
                'urgency_level' => 'critical',
                'risk_level' => 'high',
            ],
            'operational_insights_json' => [
                'escalation_risks' => ['شکایت به مدیریت'],
                'follow_up_suggestions' => ['تماس بازخورد امروز ساعت ۱۷'],
            ],
            'concerns_json' => [[
                'type' => 'service',
                'text' => 'قطع سرویس',
                'severity' => 'high',
            ]],
            'next_actions_json' => ['ثبت تیکت فوری برای قطع سرویس'],
        ]);

        $actions = CustomerNextActionAggregator::prioritized([$routine, $critical]);

        $this->assertSame('ثبت تیکت فوری برای قطع سرویس', $actions[0]);
        $this->assertContains('تماس بازخورد امروز ساعت ۱۷', $actions);
        $this->assertCount(5, $actions);
    }

    public function test_it_ranks_overdue_follow_ups_ahead_of_later_week_actions(): void
    {
        Carbon::setTestNow('2026-09-18 10:00:00');

        $overdue = $this->analysis([
            'analyzed_at' => now()->subDays(10),
            'next_actions_json' => ['تماس پیگیری فردا برای ارسال قرارداد'],
        ]);

        $later = $this->analysis([
            'analyzed_at' => now(),
            'next_actions_json' => ['پیگیری هفته آینده'],
        ]);

        $actions = CustomerNextActionAggregator::prioritized([$later, $overdue]);

        $this->assertSame('تماس پیگیری فردا برای ارسال قرارداد', $actions[0]);
        $this->assertSame('پیگیری هفته آینده', $actions[1]);
    }

    public function test_it_collapses_duplicate_follow_up_suggestions(): void
    {
        $analysis = $this->analysis([
            'next_actions_json' => ['ارسال پیش‌فاکتور امروز'],
            'operational_insights_json' => [
                'follow_up_suggestions' => ['ارسال پیش‌فاکتور امروز'],
            ],
        ]);

        $this->assertSame(
            ['ارسال پیش‌فاکتور امروز'],
            CustomerNextActionAggregator::prioritized([$analysis]),
        );
    }

    /** @param  array<string, mixed>  $attributes */
    private function analysis(array $attributes): ConversationAnalysis
    {
        return new ConversationAnalysis(array_merge([
            'score' => 70,
            'summary' => 'خلاصه تست',
            'sentiment' => AnalysisSentiment::Neutral,
            'needs_attention' => false,
            'strengths_json' => [],
            'weaknesses_json' => [],
            'next_actions_json' => [],
            'customer_insights_json' => [],
            'operational_insights_json' => [],
            'concerns_json' => [],
            'analyzed_at' => now(),
        ], $attributes));
    }
}
