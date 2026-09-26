<?php

namespace Tests\Feature;

use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\DTOs\AnalysisListFilter;
use App\DTOs\ReportFilter;
use App\Enums\ReportDatePreset;
use App\Enums\UserRole;
use App\Infrastructure\Voip\Adapters\SimotelVoipAdapter;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\EmployeeIntegrationMeta;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\OrganizationVoipConnection;
use App\Models\User;
use App\Models\VoipProvider;
use App\Services\AnalysisListQuery;
use App\Services\EmployeeDashboardAnalytics;
use App\Services\Performance\EmployeePerformanceAnalytics;
use App\Support\CompanyWorkCalendar;
use App\Support\JalaliDate;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChartHolidaySyncTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_and_call_analysis_charts_skip_weekday_holidays_and_days_without_extension_calls(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-16 12:00:00', CompanyWorkCalendar::TIMEZONE));

        $organization = Organization::factory()->create();
        $employee = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
            'first_name' => 'سارا',
            'last_name' => 'کریمی',
            'is_active' => true,
        ]);
        $connection = $this->extensionConnection($organization, $employee, '101');

        $monday = Carbon::parse('2026-09-14 11:00:00', CompanyWorkCalendar::TIMEZONE);
        $tuesday = Carbon::parse('2026-09-15 11:00:00', CompanyWorkCalendar::TIMEZONE);
        $thursday = Carbon::parse('2026-09-10 11:00:00', CompanyWorkCalendar::TIMEZONE);
        $today = Carbon::parse('2026-09-16 10:00:00', CompanyWorkCalendar::TIMEZONE);

        $this->seedAnalysis($organization, $employee, $monday, $connection, '101');
        $this->seedAnalysis($organization, $employee, $tuesday);
        $this->seedAnalysis($organization, $employee, $thursday, $connection, '101');
        $this->seedAnalysis($organization, $employee, $today);

        $filter = ReportFilter::make($organization->id, ReportDatePreset::Last30);
        $dashboardPeriods = collect(app(EmployeePerformanceAnalytics::class)->teamDashboard($filter)['quality_trend'])
            ->pluck('period')
            ->all();

        $this->assertContains('2026-09-14', $dashboardPeriods);
        $this->assertNotContains('2026-09-16', $dashboardPeriods);
        $this->assertNotContains('2026-09-15', $dashboardPeriods);
        $this->assertNotContains('2026-09-10', $dashboardPeriods);

        $charts = app(AnalysisListQuery::class)->charts(AnalysisListFilter::make($organization->id, ReportDatePreset::Last30));
        $analysisPeriods = collect($charts['quality_trend'])->pluck('period')->all();
        $volumePeriods = collect($charts['volume_trend'])->pluck('period')->all();

        $this->assertContains('2026-09-14', $analysisPeriods);
        $this->assertNotContains('2026-09-16', $analysisPeriods);
        $weekdayLabel = JalaliDate::monthDayWithWeekday('2026-09-14');
        $this->assertSame($weekdayLabel, collect($charts['quality_trend'])->firstWhere('period', '2026-09-14')['tooltip_label']);
        $this->assertSame($weekdayLabel, collect($charts['volume_trend'])->firstWhere('period', '2026-09-14')['tooltip_label']);
        $this->assertStringContainsString('دوشنبه', $weekdayLabel);
        $this->assertNotContains('2026-09-15', $analysisPeriods);
        $this->assertNotContains('2026-09-10', $analysisPeriods);
        $this->assertSame($analysisPeriods, $volumePeriods);

        $employeePeriods = collect(EmployeeDashboardAnalytics::forEmployee($employee)->cockpit()['score_trend'])
            ->pluck('period')
            ->all();

        $this->assertContains('2026-09-14', $employeePeriods);
        $this->assertNotContains('2026-09-16', $employeePeriods);
        $this->assertNotContains('2026-09-15', $employeePeriods);
        $this->assertNotContains('2026-09-10', $employeePeriods);
    }

    public function test_days_without_calls_stay_on_charts_until_extensions_are_registered(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-16 12:00:00', CompanyWorkCalendar::TIMEZONE));

        $organization = Organization::factory()->create();
        $employee = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
            'first_name' => 'سارا',
            'last_name' => 'کریمی',
            'is_active' => true,
        ]);

        $this->seedAnalysis(
            $organization,
            $employee,
            Carbon::parse('2026-09-15 11:00:00', CompanyWorkCalendar::TIMEZONE),
        );

        $periods = collect(app(EmployeePerformanceAnalytics::class)->teamDashboard(
            ReportFilter::make($organization->id, ReportDatePreset::Last30),
        )['quality_trend'])->pluck('period')->all();

        $this->assertContains('2026-09-15', $periods);
    }

    private function extensionConnection(Organization $organization, OrganizationUser $employee, string $extension): OrganizationVoipConnection
    {
        $provider = VoipProvider::query()->create([
            'name' => 'Simotel',
            'code' => VoipProviderCode::Simotel->value,
            'adapter_class' => SimotelVoipAdapter::class,
            'is_active' => true,
        ]);

        $connection = OrganizationVoipConnection::query()->create([
            'organization_id' => $organization->id,
            'voip_provider_id' => $provider->id,
            'name' => 'خط اصلی',
            'credentials' => [],
            'is_default' => true,
            'is_active' => true,
        ]);

        EmployeeIntegrationMeta::query()->create([
            'organization_user_id' => $employee->id,
            'integratable_type' => OrganizationVoipConnection::class,
            'integratable_id' => $connection->id,
            'key' => 'extension',
            'value' => $extension,
        ]);

        return $connection;
    }

    private function seedAnalysis(
        Organization $organization,
        OrganizationUser $employee,
        Carbon $at,
        ?OrganizationVoipConnection $connection = null,
        ?string $extension = null,
    ): void {
        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'organization_voip_connection_id' => $connection?->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'simotel',
            'external_call_id' => uniqid('chart-holiday-', true),
            'direction' => 'inbound',
            'caller_number' => '09120000000',
            'receiver_number' => $extension ?? '02100000000',
            'status' => 'completed',
            'processing_status' => 'analyzed',
            'duration_seconds' => 90,
            'started_at' => $at->copy()->utc(),
        ]);

        ConversationAnalysis::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'call_id' => $call->id,
            'source' => ConversationSource::Voip,
            'llm_provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'score' => 80,
            'is_evaluable' => true,
            'summary' => 'خلاصه',
            'sentiment' => AnalysisSentiment::Neutral,
            'strengths_json' => [],
            'weaknesses_json' => [],
            'next_actions_json' => [],
            'lead_quality_json' => ['score' => 60, 'level' => 'medium', 'reason' => 'test'],
            'analyzed_at' => $at->copy()->utc(),
        ]);
    }
}
