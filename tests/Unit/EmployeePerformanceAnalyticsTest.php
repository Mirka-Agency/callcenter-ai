<?php

namespace Tests\Unit;

use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\DTOs\AnalysisListFilter;
use App\DTOs\ReportFilter;
use App\Enums\Gender;
use App\Enums\ReportDatePreset;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\AnalysisListQuery;
use App\Services\CallIntake\CallIntakeSettings;
use App\Services\CallIntake\Filters\InternalAgentCallsFilter;
use App\Services\Performance\EmployeePerformanceAnalytics;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeePerformanceAnalyticsTest extends TestCase
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

    public function test_team_dashboard_returns_employee_summaries(): void
    {
        [$organization, $employee] = $this->seedEmployeeWithAnalysis(score: 85);

        $filter = ReportFilter::make($organization->id, ReportDatePreset::Last30);
        $dashboard = app(EmployeePerformanceAnalytics::class)->teamDashboard($filter);

        $this->assertArrayHasKey('kpis', $dashboard);
        $this->assertArrayHasKey('employees', $dashboard);
        $this->assertArrayHasKey('executive_summary', $dashboard);
        $this->assertNotEmpty($dashboard['employees']);
        $this->assertSame($employee->full_name, $dashboard['employees'][0]['name']);
    }

    public function test_team_score_cards_follow_disabled_intake_filters(): void
    {
        [$organization, $employee] = $this->seedEmployeeWithAnalysis(score: 80);

        $internal = Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'novatel',
            'external_call_id' => 'perf-internal',
            'direction' => 'inbound',
            'caller_number' => '111',
            'receiver_number' => '112',
            'status' => 'completed',
            'processing_status' => 'analyzed',
            'duration_seconds' => 40,
            'started_at' => now()->subDay(),
        ]);
        Call::query()->whereKey($internal->id)->update(['is_internal_agent_call' => true]);

        ConversationAnalysis::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'call_id' => $internal->id,
            'source' => ConversationSource::Voip,
            'llm_provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'score' => 20,
            'is_evaluable' => true,
            'summary' => 'تماس داخلی',
            'sentiment' => AnalysisSentiment::Neutral,
            'strengths_json' => [],
            'weaknesses_json' => [],
            'next_actions_json' => [],
            'lead_quality_json' => ['score' => 10, 'level' => 'low', 'reason' => 'internal'],
            'analyzed_at' => now(),
        ]);

        $organization->update([
            'call_intake_filters' => [
                'unassigned_agent_calls' => true,
                InternalAgentCallsFilter::KEY => false,
            ],
        ]);
        app(CallIntakeSettings::class)->forget($organization->id);

        $dashboard = app(EmployeePerformanceAnalytics::class)->teamDashboard(
            ReportFilter::make($organization->id, ReportDatePreset::Last30),
        );

        $this->assertSame(1, $dashboard['kpis']['total_analyzed']);
        $this->assertSame(80.0, $dashboard['kpis']['average_quality_score']);
    }

    public function test_team_dashboard_keeps_gender_so_avatars_can_be_colored(): void
    {
        $organization = Organization::factory()->create();
        $female = $this->seedNamedEmployee($organization, 'زهرا', 'کریمی', Gender::Female);
        $male = $this->seedNamedEmployee($organization, 'علی', 'محمدی', Gender::Male);
        $this->seedAnalysisForEmployee($organization, $female, 80);
        $this->seedAnalysisForEmployee($organization, $male, 70);

        $filter = ReportFilter::make($organization->id, ReportDatePreset::Last30);
        $employees = collect(app(EmployeePerformanceAnalytics::class)->teamDashboard($filter)['employees'])
            ->keyBy('id');

        $this->assertSame('female', $employees[$female->id]['gender']);
        $this->assertSame('male', $employees[$male->id]['gender']);
    }

    public function test_employee_profile_includes_recent_calls_and_coaching(): void
    {
        [$organization, $employee] = $this->seedEmployeeWithAnalysis(score: 72);

        $filter = ReportFilter::make($organization->id, ReportDatePreset::Last30, employeeIds: [$employee->id]);
        $profile = app(EmployeePerformanceAnalytics::class)->employeeProfile($filter, $employee);

        $this->assertSame($employee->id, $profile['employee']['id']);
        $this->assertSame($employee->user?->email, $profile['employee']['email']);
        $this->assertGreaterThan(0, $profile['metrics']['total_analyzed']);
        $this->assertNotEmpty($profile['recent_calls']);
        $this->assertArrayHasKey('training_areas', $profile['coaching']);
        $this->assertNotEmpty($profile['executive_summary']);
    }

    public function test_zero_score_calls_are_excluded_from_quality_average(): void
    {
        [$organization, $employee] = $this->seedEmployeeWithAnalysis(score: 80);
        $this->seedAnalysisForEmployee($organization, $employee, score: 0);

        $filter = ReportFilter::make($organization->id, ReportDatePreset::Last30);
        $dashboard = app(EmployeePerformanceAnalytics::class)->teamDashboard($filter);

        $this->assertSame(80.0, $dashboard['kpis']['average_quality_score']);
        $this->assertSame(80.0, $dashboard['employees'][0]['average_score']);
        $this->assertSame(2, $dashboard['kpis']['total_analyzed']);
        $this->assertSame(1, $dashboard['kpis']['quality_sample_count']);
        $this->assertSame(1, $dashboard['kpis']['lead_sample_count']);
        $this->assertSame(1, $dashboard['kpis']['sentiment_sample_count']);
    }

    public function test_total_analyzed_follows_call_occurrence_like_analysis_list(): void
    {
        $organization = Organization::factory()->create();
        $employee = $this->seedNamedEmployee($organization, 'سارا', 'نوری');

        // Call outside the window, analyzed today → does not count.
        $this->seedAnalysisForEmployee(
            $organization,
            $employee,
            score: 88,
            analyzedAt: now(),
            callStartedAt: now()->subDays(40),
        );

        // Call inside the window (even if analyzed later the same day) → counts.
        $this->seedAnalysisForEmployee(
            $organization,
            $employee,
            score: 70,
            analyzedAt: now(),
            callStartedAt: now()->subDays(5),
        );

        $filter = ReportFilter::make($organization->id, ReportDatePreset::Last30);
        $dashboard = app(EmployeePerformanceAnalytics::class)->teamDashboard($filter);
        $analysisListTotal = app(AnalysisListQuery::class)->analyzedCallCount(
            AnalysisListFilter::make($organization->id, ReportDatePreset::Last30),
        );

        $this->assertSame(1, $dashboard['kpis']['total_analyzed']);
        $this->assertSame($analysisListTotal, $dashboard['kpis']['total_analyzed']);
    }

    public function test_report_date_preset_includes_quarter_and_year(): void
    {
        $this->assertContains(ReportDatePreset::CurrentQuarter, ReportDatePreset::selectable());
        $this->assertContains(ReportDatePreset::CurrentYear, ReportDatePreset::selectable());
    }

    public function test_employee_summary_keeps_only_the_most_frequent_strength_and_weakness(): void
    {
        $organization = Organization::factory()->create();
        $employee = $this->seedNamedEmployee($organization, 'نگار', 'صادقی');

        $this->seedAnalysisForEmployee($organization, $employee, 78, ['پیگیری ضعیف', 'جمع‌بندی ضعیف'], strengths: ['لحن محترمانه', 'گوش دادن فعال']);
        $this->seedAnalysisForEmployee($organization, $employee, 74, ['پیگیری ضعیف'], strengths: ['لحن محترمانه']);
        $this->seedAnalysisForEmployee($organization, $employee, 81, ['توضیح ناقص محصول'], strengths: ['شروع مناسب تماس']);

        $filter = ReportFilter::make($organization->id, ReportDatePreset::Last30);
        $card = collect(app(EmployeePerformanceAnalytics::class)->teamDashboard($filter)['employees'])
            ->firstWhere('id', $employee->id);

        $this->assertSame('لحن محترمانه', $card['top_strength']);
        $this->assertSame('پیگیری ضعیف', $card['top_weakness']);
    }

    public function test_team_dashboard_flags_agents_with_repeated_weaknesses(): void
    {
        $organization = Organization::factory()->create();
        $repeatedAgent = $this->seedNamedEmployee($organization, 'رضا', 'کریمی');
        $oneOffAgent = $this->seedNamedEmployee($organization, 'سارا', 'محمدی');
        $singleRepeatAgent = $this->seedNamedEmployee($organization, 'مینا', 'رضایی');

        $this->seedAnalysisForEmployee($organization, $repeatedAgent, 55, ['پیگیری ضعیف', 'جمع‌بندی ضعیف']);
        $this->seedAnalysisForEmployee($organization, $repeatedAgent, 58, ['پیگیری ضعیف', 'عدم تأیید نیاز']);
        $this->seedAnalysisForEmployee($organization, $repeatedAgent, 52, ['جمع‌بندی ضعیف']);

        $this->seedAnalysisForEmployee($organization, $oneOffAgent, 50, ['قطع مکالمه']);
        $this->seedAnalysisForEmployee($organization, $oneOffAgent, 48, ['توضیح ناقص محصول']);
        $this->seedAnalysisForEmployee($organization, $oneOffAgent, 49, ['لحن نامناسب']);

        $this->seedAnalysisForEmployee($organization, $singleRepeatAgent, 60, ['عدم معرفی خود']);
        $this->seedAnalysisForEmployee($organization, $singleRepeatAgent, 61, ['عدم معرفی خود']);

        $filter = ReportFilter::make($organization->id, ReportDatePreset::Last30);
        $dashboard = app(EmployeePerformanceAnalytics::class)->teamDashboard($filter);
        $attention = collect($dashboard['attention_employees']);

        $this->assertTrue($attention->contains('id', $repeatedAgent->id));
        $this->assertFalse($attention->contains('id', $oneOffAgent->id));
        $this->assertFalse($attention->contains('id', $singleRepeatAgent->id));

        $card = $attention->firstWhere('id', $repeatedAgent->id);
        $this->assertEqualsCanonicalizing(['پیگیری ضعیف', 'جمع‌بندی ضعیف'], collect($card['repeated_weaknesses'])->pluck('item')->all());
        $this->assertSame(2, $card['repeated_weakness_count']);
        $this->assertSame(4, $card['repeated_weakness_occurrences']);
    }

    public function test_team_dashboard_flags_agent_with_one_heavily_repeated_weakness(): void
    {
        $organization = Organization::factory()->create();
        $agent = $this->seedNamedEmployee($organization, 'حامد', 'نوری');

        $this->seedAnalysisForEmployee($organization, $agent, 54, ['پیگیری ضعیف']);
        $this->seedAnalysisForEmployee($organization, $agent, 51, ['پیگیری ضعیف']);
        $this->seedAnalysisForEmployee($organization, $agent, 49, ['پیگیری ضعیف']);

        $filter = ReportFilter::make($organization->id, ReportDatePreset::Last30);
        $dashboard = app(EmployeePerformanceAnalytics::class)->teamDashboard($filter);
        $attention = collect($dashboard['attention_employees']);

        $this->assertTrue($attention->contains('id', $agent->id));
        $this->assertSame(3, $attention->firstWhere('id', $agent->id)['repeated_weaknesses'][0]['count']);
    }

    public function test_team_weaknesses_skip_unanswered_calls_and_extension_redirects(): void
    {
        $organization = Organization::factory()->create();
        $agent = $this->seedNamedEmployee($organization, 'نیما', 'کاظمی');

        $this->seedAnalysisForEmployee(
            $organization,
            $agent,
            40,
            ['عدم ارتباط با مشتری'],
            'کارشناس زنگ زد ولی مشتری پاسخ نداد و مکالمه‌ای شکل نگرفت',
        );
        $this->seedAnalysisForEmployee(
            $organization,
            $agent,
            70,
            ['عدم پیگیری برای اتصال مستقیم مشتری به بخش مربوطه'],
            'کارمند شرکت گفت با داخلی دیگری تماس بگیرید و تماس قطع شد',
        );
        $this->seedAnalysisForEmployee(
            $organization,
            $agent,
            61,
            ['جمع‌بندی ضعیف انتهای تماس'],
            'مشتری درباره قیمت پرسید و کارشناس جمع‌بندی نکرد',
        );

        $filter = ReportFilter::make($organization->id, ReportDatePreset::Last30);
        $items = collect(app(EmployeePerformanceAnalytics::class)->teamDashboard($filter)['team_weaknesses'])
            ->pluck('item')
            ->all();

        $this->assertSame(['جمع‌بندی ضعیف انتهای تماس'], $items);
    }

    public function test_team_weakness_trend_compares_counts_with_the_previous_period(): void
    {
        $organization = Organization::factory()->create();
        $agent = $this->seedNamedEmployee($organization, 'نیما', 'کاظمی');
        $previous = now()->subDays(42);

        foreach (range(1, 4) as $ignored) {
            $this->seedAnalysisForEmployee($organization, $agent, 60, ['جمع‌بندی ضعیف مکالمه'], analyzedAt: $previous);
        }

        foreach (range(1, 2) as $ignored) {
            $this->seedAnalysisForEmployee($organization, $agent, 60, ['پاسخ‌گویی ناقص'], analyzedAt: $previous);
        }

        foreach (range(1, 5) as $ignored) {
            $this->seedAnalysisForEmployee($organization, $agent, 60, ['جمع‌بندی ضعیف مکالمه']);
        }

        foreach (range(1, 1) as $ignored) {
            $this->seedAnalysisForEmployee($organization, $agent, 60, ['پاسخ‌گویی ناقص']);
        }

        $this->seedAnalysisForEmployee($organization, $agent, 60, ['فرصت فروش مکمل از دست رفت']);

        $rows = collect(app(EmployeePerformanceAnalytics::class)->teamDashboard(
            ReportFilter::make($organization->id, ReportDatePreset::Last30),
        )['team_weaknesses'])->keyBy('item');

        $this->assertSame(5, $rows['جمع‌بندی ضعیف مکالمه']['count']);
        $this->assertSame(25, $rows['جمع‌بندی ضعیف مکالمه']['trend']);
        $this->assertSame(1, $rows['پاسخ‌گویی ناقص']['count']);
        $this->assertSame(-50, $rows['پاسخ‌گویی ناقص']['trend']);
        $this->assertSame(100, $rows['فرصت فروش مکمل از دست رفت']['trend']);
    }

    public function test_team_dashboard_excludes_agents_with_sparse_repeated_weaknesses(): void
    {
        $organization = Organization::factory()->create();
        $agent = $this->seedNamedEmployee($organization, 'مریم', 'جعفری');

        $this->seedAnalysisForEmployee($organization, $agent, 82, ['پیگیری ضعیف']);
        $this->seedAnalysisForEmployee($organization, $agent, 80, ['پیگیری ضعیف']);
        $this->seedAnalysisForEmployee($organization, $agent, 84, ['جمع‌بندی ضعیف']);
        $this->seedAnalysisForEmployee($organization, $agent, 81, ['جمع‌بندی ضعیف']);
        $this->seedAnalysisForEmployee($organization, $agent, 83, ['گوش دادن فعال']);
        $this->seedAnalysisForEmployee($organization, $agent, 85, ['توضیح کامل محصول']);
        $this->seedAnalysisForEmployee($organization, $agent, 86, ['همدلی مناسب']);
        $this->seedAnalysisForEmployee($organization, $agent, 84, ['جمع‌بندی خوب']);

        $filter = ReportFilter::make($organization->id, ReportDatePreset::Last30);
        $dashboard = app(EmployeePerformanceAnalytics::class)->teamDashboard($filter);

        $this->assertFalse(collect($dashboard['attention_employees'])->contains('id', $agent->id));
    }

    /** @return array{0: Organization, 1: OrganizationUser} */
    private function seedEmployeeWithAnalysis(int $score): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $employee = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'first_name' => 'علی',
            'last_name' => 'احمدی',
            'is_active' => true,
        ]);

        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'novatel',
            'external_call_id' => 'perf-test-1',
            'direction' => 'inbound',
            'caller_number' => '09121234567',
            'receiver_number' => '02100000000',
            'status' => 'completed',
            'processing_status' => 'analyzed',
            'duration_seconds' => 180,
            'started_at' => now()->subDay(),
        ]);

        ConversationAnalysis::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'call_id' => $call->id,
            'source' => ConversationSource::Voip,
            'llm_provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'score' => $score,
            'is_evaluable' => $score > 0,
            'summary' => 'خلاصه تست',
            'sentiment' => AnalysisSentiment::Positive,
            'strengths_json' => ['گوش دادن فعال'],
            'weaknesses_json' => ['پیگیری ضعیف'],
            'next_actions_json' => [],
            'lead_quality_json' => ['score' => 75, 'level' => 'high', 'reason' => 'test'],
            'analyzed_at' => now(),
        ]);

        return [$organization, $employee];
    }

    private function seedNamedEmployee(
        Organization $organization,
        string $firstName,
        string $lastName,
        ?Gender $gender = null,
    ): OrganizationUser {
        return OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create()->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => $gender,
            'is_active' => true,
        ]);
    }

    /**
     * @param  list<string>  $weaknesses
     * @param  list<string>  $strengths
     */
    private function seedAnalysisForEmployee(
        Organization $organization,
        OrganizationUser $employee,
        int $score,
        array $weaknesses = [],
        string $summary = 'تماس بدون مکالمه',
        ?Carbon $analyzedAt = null,
        array $strengths = [],
        ?Carbon $callStartedAt = null,
    ): void {
        $analyzedAt ??= now();
        $callStartedAt ??= $analyzedAt;

        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'novatel',
            'external_call_id' => uniqid('perf-zero-', true),
            'direction' => 'inbound',
            'caller_number' => '09120000000',
            'receiver_number' => '02100000000',
            'status' => 'completed',
            'processing_status' => 'analyzed',
            'duration_seconds' => 12,
            'started_at' => $callStartedAt,
        ]);

        ConversationAnalysis::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'call_id' => $call->id,
            'source' => ConversationSource::Voip,
            'llm_provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'score' => $score,
            'is_evaluable' => $score > 0,
            'summary' => $summary,
            'sentiment' => AnalysisSentiment::Neutral,
            'strengths_json' => $strengths,
            'weaknesses_json' => $weaknesses,
            'next_actions_json' => [],
            'analyzed_at' => $analyzedAt,
        ]);
    }
}
