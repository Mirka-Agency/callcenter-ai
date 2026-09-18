<?php

namespace Tests\Unit;

use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\DTOs\ReportFilter;
use App\Enums\ReportDatePreset;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\Performance\EmployeePerformanceAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeePerformanceAnalyticsTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_employee_profile_includes_recent_calls_and_coaching(): void
    {
        [$organization, $employee] = $this->seedEmployeeWithAnalysis(score: 72);

        $filter = ReportFilter::make($organization->id, ReportDatePreset::Last30, employeeIds: [$employee->id]);
        $profile = app(EmployeePerformanceAnalytics::class)->employeeProfile($filter, $employee);

        $this->assertSame($employee->id, $profile['employee']['id']);
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
    }

    public function test_report_date_preset_includes_quarter_and_year(): void
    {
        $this->assertContains(ReportDatePreset::CurrentQuarter, ReportDatePreset::selectable());
        $this->assertContains(ReportDatePreset::CurrentYear, ReportDatePreset::selectable());
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

    private function seedNamedEmployee(Organization $organization, string $firstName, string $lastName): OrganizationUser
    {
        return OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create()->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'is_active' => true,
        ]);
    }

    /** @param  list<string>  $weaknesses */
    private function seedAnalysisForEmployee(
        Organization $organization,
        OrganizationUser $employee,
        int $score,
        array $weaknesses = [],
    ): void {
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
            'started_at' => now()->subHours(2),
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
            'summary' => 'تماس بدون مکالمه',
            'sentiment' => AnalysisSentiment::Neutral,
            'strengths_json' => [],
            'weaknesses_json' => $weaknesses,
            'next_actions_json' => [],
            'analyzed_at' => now(),
        ]);
    }
}
