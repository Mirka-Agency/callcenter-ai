<?php

namespace Tests\Feature;

use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\DTOs\ReportFilter;
use App\Enums\ReportDatePreset;
use App\Enums\UserRole;
use App\Livewire\Employer\Dashboard\Overview;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\Performance\EmployeePerformanceAnalytics;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QualityTrendPointInsightTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_click_shows_increase_reason_and_contributing_agents(): void
    {
        $organization = $this->actingAsEmployer();
        $rising = $this->seedEmployee($organization, 'سارا', 'کریمی');
        $stable = $this->seedEmployee($organization, 'رضا', 'نوری');

        $previousDay = now()->subDays(2)->startOfDay()->addHours(10);
        $selectedDay = now()->subDay()->startOfDay()->addHours(10);

        $this->seedAnalysis($organization, $rising, 52, $previousDay, strengths: [], weaknesses: ['پیگیری ضعیف']);
        $this->seedAnalysis($organization, $stable, 80, $previousDay, strengths: ['لحن حرفه‌ای'], weaknesses: []);
        $this->seedAnalysis($organization, $rising, 91, $selectedDay, strengths: ['گوش دادن فعال'], weaknesses: []);
        $this->seedAnalysis($organization, $stable, 81, $selectedDay, strengths: ['لحن حرفه‌ای'], weaknesses: []);

        $period = $selectedDay->format('Y-m-d');
        $insight = app(EmployeePerformanceAnalytics::class)
            ->qualityTrendPointInsight(ReportFilter::make($organization->id, ReportDatePreset::Last30), $period);

        $this->assertNotNull($insight);
        $this->assertSame('up', $insight['direction']);
        $this->assertArrayHasKey($period, app(EmployeePerformanceAnalytics::class)->teamDashboard(ReportFilter::make($organization->id, ReportDatePreset::Last30))['quality_trend_insights']);
        $this->assertStringContainsString('افزایش داشت، به این دلیل که', $insight['reason']);
        $this->assertStringContainsString('گوش دادن فعال', $insight['reason']);
        $this->assertSame(['سارا کریمی'], collect($insight['agents'])->pluck('name')->all());

        $component = Livewire::test(Overview::class)
            ->assertSee('روند کیفیت تیم')
            ->assertSee('برای دیدن دلیل تغییر، روی یک نقطه کلیک کنید')
            ->assertDontSee('کارشناسانی که باعث افزایش روند شدند')
            ->call('drilldown', 'period', $period)
            ->assertSet('selectedQualityTrendPeriod', $period)
            ->assertSee('افزایش کیفیت')
            ->assertSee('افزایش داشت، به این دلیل که')
            ->assertSee('گوش دادن فعال')
            ->assertSee('کارشناسانی که باعث افزایش روند شدند');

        $this->assertStringContainsString('id="dashboard-quality-trend"', $component->html());
        $this->assertDoesNotMatchRegularExpression(
            '/id="dashboard-quality-trend"[^>]*\bh-full\b/',
            $component->html(),
        );

        $agentsSection = mb_substr($component->html(), (int) mb_strpos($component->html(), 'کارشناسانی که باعث افزایش روند شدند'));
        $this->assertStringContainsString('سارا کریمی', $agentsSection);
        $this->assertStringNotContainsString('رضا نوری', $agentsSection);

        $component
            ->call('clearQualityTrendPeriod')
            ->assertSet('selectedQualityTrendPeriod', null)
            ->assertDontSee('کارشناسانی که باعث افزایش روند شدند');
    }

    public function test_dashboard_click_shows_decrease_reason_and_responsible_agents(): void
    {
        $organization = $this->actingAsEmployer();
        $dropping = $this->seedEmployee($organization, 'مینا', 'کاظمی');
        $steady = $this->seedEmployee($organization, 'حامد', 'رضایی');

        $previousDay = now()->subDays(2)->startOfDay()->addHours(11);
        $selectedDay = now()->subDay()->startOfDay()->addHours(11);

        $this->seedAnalysis($organization, $dropping, 88, $previousDay, strengths: ['جمع‌بندی قوی'], weaknesses: []);
        $this->seedAnalysis($organization, $steady, 84, $previousDay, strengths: ['لحن حرفه‌ای'], weaknesses: []);
        $this->seedAnalysis($organization, $dropping, 41, $selectedDay, strengths: [], weaknesses: ['جمع‌بندی ضعیف انتهای تماس']);
        $this->seedAnalysis($organization, $steady, 83, $selectedDay, strengths: ['لحن حرفه‌ای'], weaknesses: []);

        $period = $selectedDay->format('Y-m-d');

        $html = Livewire::test(Overview::class)
            ->call('drilldown', 'period', $period)
            ->assertSee('کاهش کیفیت')
            ->assertSee('کاهش داشت، به این دلیل که')
            ->assertSee('جمع‌بندی ضعیف انتهای تماس')
            ->assertSee('کارشناسانی که باعث کاهش روند شدند')
            ->html();

        $agentsSection = mb_substr($html, (int) mb_strpos($html, 'کارشناسانی که باعث کاهش روند شدند'));
        $this->assertStringContainsString('مینا کاظمی', $agentsSection);
        $this->assertStringNotContainsString('حامد رضایی', $agentsSection);
    }

    public function test_insight_rejects_unknown_period_and_toggles_the_same_point_off(): void
    {
        $organization = $this->actingAsEmployer();
        $employee = $this->seedEmployee($organization, 'علی', 'احمدی');
        $this->seedAnalysis($organization, $employee, 70, now()->subDay(), strengths: ['گوش دادن فعال']);

        $this->assertNull(
            app(EmployeePerformanceAnalytics::class)->qualityTrendPointInsight(
                ReportFilter::make($organization->id, ReportDatePreset::Last30),
                '1999-01-01',
            ),
        );

        $period = now()->subDay()->format('Y-m-d');

        Livewire::test(Overview::class)
            ->call('drilldown', 'employee', '1')
            ->assertSet('selectedQualityTrendPeriod', null)
            ->call('drilldown', 'period', $period)
            ->assertSet('selectedQualityTrendPeriod', $period)
            ->call('drilldown', 'period', $period)
            ->assertSet('selectedQualityTrendPeriod', null);
    }

    public function test_quality_trend_omits_thursday_and_friday_points(): void
    {
        $organization = $this->actingAsEmployer();
        $employee = $this->seedEmployee($organization, 'پریسا', 'اکبری');

        $friday = now('Asia/Tehran')->previous(\Carbon\Carbon::FRIDAY)->setTime(11, 0);
        $thursday = $friday->copy()->subDay()->setTime(11, 0);
        $wednesday = $thursday->copy()->subDay()->setTime(11, 0);

        $this->seedAnalysis(
            $organization,
            $employee,
            55,
            analyzedAt: $friday->copy()->utc(),
            strengths: ['پیگیری'],
            startedAt: $friday->copy()->utc(),
        );
        $this->seedAnalysis(
            $organization,
            $employee,
            82,
            analyzedAt: $thursday->copy()->utc(),
            strengths: ['گوش دادن فعال'],
            startedAt: $thursday->copy()->utc(),
        );
        $this->seedAnalysis(
            $organization,
            $employee,
            70,
            analyzedAt: $wednesday->copy()->utc(),
            strengths: ['جمع‌بندی'],
            startedAt: $wednesday->copy()->utc(),
        );

        $analytics = app(EmployeePerformanceAnalytics::class);
        $dashboard = $analytics->teamDashboard(ReportFilter::make($organization->id, ReportDatePreset::Last30));
        $periods = collect($dashboard['quality_trend'])->pluck('period')->all();

        $this->assertNotContains($friday->toDateString(), $periods);
        $this->assertNotContains($thursday->toDateString(), $periods);
        $this->assertContains($wednesday->toDateString(), $periods);
        $this->assertSame(70.0, collect($dashboard['quality_trend'])->firstWhere('period', $wednesday->toDateString())['avg_score']);
        $this->assertNull(
            $analytics->qualityTrendPointInsight(
                ReportFilter::make($organization->id, ReportDatePreset::Last30),
                $friday->toDateString(),
            ),
        );
        $this->assertNull(
            $analytics->qualityTrendPointInsight(
                ReportFilter::make($organization->id, ReportDatePreset::Last30),
                $thursday->toDateString(),
            ),
        );
    }

    public function test_quality_trend_omits_holiday_calls_when_utc_and_tehran_weekdays_differ(): void
    {
        $organization = $this->actingAsEmployer();
        $employee = $this->seedEmployee($organization, 'هستی', 'راد');

        $thursdayEvening = Carbon::parse('2026-09-17 22:30:00', 'UTC');
        $earlyThursday = Carbon::parse('2026-09-17 00:40:00', 'Asia/Tehran');
        $friday = Carbon::parse('2026-09-18 11:00:00', 'Asia/Tehran');

        $this->seedAnalysis(
            $organization,
            $employee,
            80,
            analyzedAt: $thursdayEvening->copy(),
            strengths: ['پیگیری'],
            startedAt: $thursdayEvening->copy(),
        );
        $this->seedAnalysis(
            $organization,
            $employee,
            90,
            analyzedAt: $earlyThursday->copy()->utc(),
            strengths: ['جمع‌بندی'],
            startedAt: $earlyThursday->copy()->utc(),
        );
        $this->seedAnalysis(
            $organization,
            $employee,
            40,
            analyzedAt: $friday->copy()->utc(),
            strengths: ['لحن'],
            startedAt: $friday->copy()->utc(),
        );

        $periods = collect(app(EmployeePerformanceAnalytics::class)
            ->teamDashboard(ReportFilter::make($organization->id, ReportDatePreset::Last30))['quality_trend'])
            ->pluck('period')
            ->all();

        $this->assertNotContains('2026-09-17', $periods);
        $this->assertNotContains('2026-09-18', $periods);
    }

    public function test_quality_trend_buckets_by_call_day_not_analysis_day(): void
    {
        $organization = $this->actingAsEmployer();
        $employee = $this->seedEmployee($organization, 'نرگس', 'موسوی');

        $callDay = now('Asia/Tehran')->subDays(3)->setTime(11, 0);
        while (\App\Support\CompanyWorkCalendar::isHoliday($callDay->toDateString())) {
            $callDay->subDay();
        }
        $analysisDay = $callDay->copy()->addDay()->setTime(9, 0);
        while (\App\Support\CompanyWorkCalendar::isHoliday($analysisDay->toDateString())) {
            $analysisDay->addDay();
        }

        $this->seedAnalysis(
            $organization,
            $employee,
            77,
            analyzedAt: $analysisDay->copy()->utc(),
            strengths: ['جمع‌بندی قوی'],
            startedAt: $callDay->copy()->utc(),
        );

        $analytics = app(EmployeePerformanceAnalytics::class);
        $dashboard = $analytics->teamDashboard(ReportFilter::make($organization->id, ReportDatePreset::Last30));
        $periods = collect($dashboard['quality_trend'])->pluck('period')->all();

        $this->assertContains($callDay->toDateString(), $periods);
        $this->assertNotContains($analysisDay->toDateString(), $periods);

        $insight = $analytics->qualityTrendPointInsight(
            ReportFilter::make($organization->id, ReportDatePreset::Last30),
            $callDay->toDateString(),
        );

        $this->assertNotNull($insight);
        $this->assertSame(77.0, $insight['current_score']);
        $this->assertSame(1, $insight['analyzed_count']);

        $this->assertNull(
            $analytics->qualityTrendPointInsight(
                ReportFilter::make($organization->id, ReportDatePreset::Last30),
                $analysisDay->toDateString(),
            ),
        );
    }

    private function actingAsEmployer(): Organization
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $employer->id]);

        $this->actingAs($employer);

        return $organization;
    }

    private function seedEmployee(Organization $organization, string $firstName, string $lastName): OrganizationUser
    {
        return OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'is_active' => true,
        ]);
    }

    /** @param  list<string>  $strengths */
    /** @param  list<string>  $weaknesses */
    private function seedAnalysis(
        Organization $organization,
        OrganizationUser $employee,
        int $score,
        $analyzedAt,
        array $strengths = [],
        array $weaknesses = [],
        $startedAt = null,
    ): void {
        $callAt = $startedAt ?? $analyzedAt;

        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'novatel',
            'external_call_id' => uniqid('quality-trend-', true),
            'direction' => 'inbound',
            'caller_number' => '09120000000',
            'receiver_number' => '02100000000',
            'status' => 'completed',
            'processing_status' => 'analyzed',
            'duration_seconds' => 140,
            'started_at' => $callAt,
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
            'summary' => 'خلاصه '.$employee->full_name,
            'sentiment' => AnalysisSentiment::Neutral,
            'strengths_json' => $strengths,
            'weaknesses_json' => $weaknesses,
            'next_actions_json' => [],
            'lead_quality_json' => ['score' => 60, 'level' => 'medium', 'reason' => 'test'],
            'analyzed_at' => $analyzedAt,
        ]);
    }
}
