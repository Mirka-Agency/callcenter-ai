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

    private function seedAnalysisForEmployee(Organization $organization, OrganizationUser $employee, int $score): void
    {
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
            'weaknesses_json' => [],
            'next_actions_json' => [],
            'analyzed_at' => now(),
        ]);
    }
}
