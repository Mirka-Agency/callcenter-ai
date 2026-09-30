<?php

namespace Tests\Feature;

use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Enums\UserRole;
use App\Livewire\Employer\Dashboard\Overview;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardWeekComparisonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-16 12:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_metric_cards_say_whether_they_rose_or_fell_versus_last_month(): void
    {
        $organization = $this->actingAsEmployer();
        $employee = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
            'first_name' => 'علی',
            'last_name' => 'احمدی',
            'is_active' => true,
        ]);

        $this->seedAnalysis($organization, $employee, Carbon::parse('2026-08-12 12:00:00', 'UTC'), 40, AnalysisSentiment::Negative, 30);
        $this->seedAnalysis($organization, $employee, now()->subDays(10), 40, AnalysisSentiment::Negative, 30);
        $this->seedAnalysis($organization, $employee, now()->subDay(), 90, AnalysisSentiment::Positive, 80);

        $html = Livewire::test(Overview::class)
            ->assertSee('خلاصه امروز')
            ->assertSee('کیفیت تیم')
            ->assertSee('/۱۰۰')
            ->assertSee('مشتری ناراضی')
            ->assertSee('کارشناسان نیازمند پیشرفت')
            ->assertSee('فرصت فروش با احتمال بالا')
            ->assertSee('جزئیات')
            ->assertDontSee('تعداد مشتری ناراضی')
            ->assertDontSee('تعداد کارشناسان نیازمند پیشرفت')
            ->assertDontSee('تعداد فرصت فروش با احتمال بالا')
            ->assertSee('میانگین امتیاز تیم')
            ->assertSee('میانگین کیفیت لید')
            ->assertSee('رضایت مشتری')
            ->assertSee('براساس ۲ تماس در ۳۰ روز گذشته محاسبه شد')
            ->assertDontSee('تماس تحلیل شده')
            ->assertSee('25 نسبت به ماه قبل')
            ->assertSee('40٪ نسبت به ماه قبل')
            ->assertDontSee('نسبت به هفته قبل')
            ->assertDontSee('25٪ نسبت به ماه قبل')
            ->assertDontSee('⬆')
            ->assertDontSee('⬇')
            ->html();

        $summaryAt = mb_strpos($html, 'خلاصه امروز');
        $trendAt = mb_strpos($html, 'روند کیفیت تیم');
        $callsTodayAt = mb_strpos($html, 'تماس‌های امروز');
        $this->assertNotFalse($summaryAt);
        $this->assertNotFalse($trendAt);
        $this->assertNotFalse($callsTodayAt);
        $this->assertLessThan($summaryAt, $callsTodayAt);
        $this->assertLessThan($trendAt, $summaryAt);

        $this->assertSame(2, mb_substr_count($html, '25 نسبت به ماه قبل'));
        $this->assertSame(1, mb_substr_count($html, '40٪ نسبت به ماه قبل'));
    }

    public function test_today_summary_lists_agents_who_need_progress_not_their_customers(): void
    {
        $organization = $this->actingAsEmployer();
        $needsProgress = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
            'first_name' => 'نگین',
            'last_name' => 'مرادی',
            'is_active' => true,
        ]);
        $steady = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
            'first_name' => 'کامران',
            'last_name' => 'یوسفی',
            'is_active' => true,
        ]);

        foreach (range(1, 3) as $ignored) {
            $this->seedAnalysis(
                $organization,
                $needsProgress,
                now()->subDay(),
                54,
                AnalysisSentiment::Neutral,
                40,
                ['پیگیری ضعیف', 'جمع‌بندی ضعیف'],
                'مشتری آزمایشی',
            );
        }

        $this->seedAnalysis(
            $organization,
            $steady,
            now()->subDay(),
            88,
            AnalysisSentiment::Positive,
            80,
            ['قطع مکالمه'],
            'مشتری پایدار',
        );
        $this->seedAnalysis(
            $organization,
            $steady,
            now()->subDays(2),
            86,
            AnalysisSentiment::Positive,
            78,
            ['توضیح ناقص محصول'],
            'مشتری پایدار',
        );

        $html = Livewire::test(Overview::class)->html();
        $start = mb_strpos($html, 'کارشناسان نیازمند پیشرفت');
        $end = mb_strpos($html, 'فرصت فروش با احتمال بالا');
        $section = mb_substr($html, (int) $start, (int) $end - (int) $start);

        $this->assertStringContainsString('نگین مرادی', $section);
        $this->assertStringContainsString(route('employer.intelligence.performance.show', $needsProgress->id), $section);
        $this->assertStringNotContainsString('مشتری آزمایشی', $section);
        $this->assertStringNotContainsString('کامران یوسفی', $section);
    }

    private function actingAsEmployer(): Organization
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $employer->id]);

        $this->actingAs($employer);

        return $organization;
    }

    /**
     * @param  list<string>  $weaknesses
     */
    private function seedAnalysis(
        Organization $organization,
        OrganizationUser $employee,
        Carbon $at,
        int $score,
        AnalysisSentiment $sentiment,
        int $leadScore,
        array $weaknesses = [],
        ?string $customerName = null,
    ): void {
        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'novatel',
            'external_call_id' => uniqid('week-compare-', true),
            'direction' => 'inbound',
            'caller_number' => '09120000000',
            'receiver_number' => '02100000000',
            'customer_name' => $customerName,
            'status' => 'completed',
            'processing_status' => 'analyzed',
            'duration_seconds' => 180,
            'started_at' => $at,
        ]);

        ConversationAnalysis::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'call_id' => $call->id,
            'source' => ConversationSource::Voip,
            'llm_provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'score' => $score,
            'is_evaluable' => true,
            'summary' => 'خلاصه تست',
            'sentiment' => $sentiment,
            'strengths_json' => [],
            'weaknesses_json' => $weaknesses,
            'next_actions_json' => [],
            'lead_quality_json' => ['score' => $leadScore, 'level' => 'high', 'reason' => 'test'],
            'analyzed_at' => $at,
        ]);
    }
}
