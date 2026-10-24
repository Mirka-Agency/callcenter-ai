<?php

namespace Tests\Feature;

use App\Domain\Call\Enums\CallProcessingStatus;
use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Filament\Widgets\PlatformAnalytics\ActiveOrganizationsChart;
use App\Filament\Widgets\PlatformAnalytics\ChurnRiskOrganizations;
use App\Filament\Widgets\PlatformAnalytics\GrowthStats;
use App\Filament\Widgets\PlatformAnalytics\PlatformPulseStats;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Organization;
use App\Models\User;
use App\Services\PlatformAnalytics\OrganizationHealth;
use App\Services\PlatformAnalytics\OrganizationHealthScorer;
use App\Services\PlatformAnalytics\PlatformMetrics;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 19 Aban 1405. Mehr 1405 is 2026-09-23..2026-10-22, Shahrivar is 2026-08-23..2026-09-22.
        $this->travelTo(Carbon::parse('2026-11-10 12:00:00'));
    }

    public function test_monthly_series_counts_active_churned_reactivated_and_new_organizations(): void
    {
        $steady = $this->organization('2026-08-01');
        $this->analysis($steady, '2026-09-01');
        $this->analysis($steady, '2026-10-01');
        $this->analysis($steady, '2026-11-01');

        $churnedNow = $this->organization('2026-08-01');
        $this->analysis($churnedNow, '2026-09-01');
        $this->analysis($churnedNow, '2026-10-01');

        $churnedLastMonth = $this->organization('2026-07-01');
        $this->analysis($churnedLastMonth, '2026-09-01');

        $returning = $this->organization('2026-06-01');
        $this->analysis($returning, '2026-07-10');
        $this->analysis($returning, '2026-11-02');

        $newThisMonth = $this->organization('2026-10-30');
        $this->analysis($newThisMonth, '2026-11-01');

        $this->organization('2026-10-01');

        $demo = $this->organization('2026-08-01', ['is_demo' => true]);
        $this->analysis($demo, '2026-09-01');
        $this->analysis($demo, '2026-10-01');

        [$mehr, $aban] = app(PlatformMetrics::class)->monthlySeries(2);

        $this->assertSame(2, $mehr['active']);
        $this->assertSame(1, $mehr['churned']);
        $this->assertEqualsWithDelta(1 / 3, $mehr['churn_rate'], 0.0001);
        $this->assertSame(0, $mehr['reactivated']);
        $this->assertSame(1, $mehr['new']);
        $this->assertFalse($mehr['is_current']);

        $this->assertSame(3, $aban['active']);
        $this->assertSame(1, $aban['churned']);
        $this->assertEqualsWithDelta(0.5, $aban['churn_rate'], 0.0001);
        $this->assertSame(1, $aban['reactivated']);
        $this->assertSame(1, $aban['new']);
        $this->assertTrue($aban['is_current']);
    }

    public function test_activation_and_cohorts_use_first_analyzed_call(): void
    {
        $activated = $this->organization('2026-10-30');
        $this->analysis($activated, '2026-11-01 00:00:00');

        $this->organization('2026-10-01');
        $this->organization('2026-11-08');

        $metrics = app(PlatformMetrics::class);
        $activation = $metrics->activation();

        $this->assertSame(2, $activation['eligible']);
        $this->assertSame(1, $activation['activated']);
        $this->assertEqualsWithDelta(0.5, $activation['rate'], 0.0001);
        $this->assertEqualsWithDelta(2.0, $activation['median_days_to_first_value'], 0.01);

        [$shahrivar, $mehr, $aban] = $metrics->cohorts(3);

        $this->assertSame(0, $shahrivar['size']);
        $this->assertSame([null, null, null], $shahrivar['retention']);
        $this->assertSame(1, $mehr['size']);
        $this->assertSame([0, 0], array_map('intval', $mehr['retention']));
        $this->assertSame(2, $aban['size']);
        $this->assertEqualsWithDelta(0.5, $aban['retention'][0], 0.0001);
    }

    public function test_health_score_flags_declining_organizations_with_reasons(): void
    {
        $healthy = $this->organization('2026-06-01', employerLastLogin: '2026-11-09');
        foreach (range(1, 5) as $day) {
            $this->analysis($healthy, "2026-11-0{$day}");
            $this->analysis($healthy, "2026-10-0{$day}");
        }
        $this->seedCall($healthy, '2026-11-05', ConversationSource::ManualUpload, CallProcessingStatus::Analyzed);

        $declining = $this->organization('2026-06-01', employerLastLogin: '2026-10-01');
        foreach (range(1, 8) as $day) {
            $this->analysis($declining, "2026-09-1{$day}");
        }
        $this->analysis($declining, '2026-10-20');
        $this->analysis($declining, '2026-10-21');

        $neverUsed = $this->organization('2026-11-01');

        $scorer = app(OrganizationHealthScorer::class);

        $this->assertSame(100, $scorer->for($healthy)->score);
        $this->assertSame(OrganizationHealth::HEALTHY, $scorer->for($healthy)->band());

        $report = $scorer->for($declining);
        $this->assertSame(OrganizationHealth::CRITICAL, $report->band());
        $this->assertSame(
            ['usage_dropped', 'no_analysis_for_days', 'no_call_intake', 'employer_inactive'],
            array_column($report->reasons, 'key'),
        );

        $this->assertSame(0, $scorer->for($neverUsed)->analysesLast30Days);
        $this->assertSame([$declining->id], $scorer->atRisk()->pluck('organizationId')->all());
    }

    public function test_super_admin_sees_dashboard_widgets_and_business_analytics_page(): void
    {
        $organization = $this->organization('2026-08-01', employerLastLogin: '2026-09-01');
        $this->analysis($organization, '2026-09-25');

        $this->actingAs(User::factory()->superAdmin()->create());

        $this->get('/admin')->assertOk();

        foreach ([PlatformPulseStats::class, GrowthStats::class, ActiveOrganizationsChart::class] as $widget) {
            Livewire::test($widget)->assertOk();
        }

        Livewire::test(ChurnRiskOrganizations::class)
            ->assertSee(__('filament.platform_analytics.churn_risk_heading'))
            ->assertSee($organization->title);

        $this->get('/admin/business-analytics')
            ->assertOk()
            ->assertSee(__('filament.platform_analytics.cohorts_heading'));

        $this->get('/admin/organizations')
            ->assertOk()
            ->assertSee(__('filament.platform_analytics.health_bands.critical'));
    }

    public function test_business_analytics_page_is_super_admin_only(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admin/business-analytics')->assertForbidden();
        $this->assertFalse(PlatformPulseStats::canView());
    }

    /** @param  array<string, mixed>  $attributes */
    private function organization(string $createdAt, array $attributes = [], ?string $employerLastLogin = null): Organization
    {
        return Organization::factory()->create([
            'created_at' => Carbon::parse($createdAt),
            'user_id' => User::factory()->employer()->create([
                'last_login_at' => $employerLastLogin ? Carbon::parse($employerLastLogin) : null,
            ])->id,
            ...$attributes,
        ]);
    }

    private function analysis(Organization $organization, string $analyzedAt): void
    {
        ConversationAnalysis::query()->create([
            'organization_id' => $organization->id,
            'source' => ConversationSource::Voip,
            'llm_provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'score' => 80,
            'is_evaluable' => true,
            'summary' => 'test',
            'sentiment' => AnalysisSentiment::Neutral,
            'strengths_json' => [],
            'weaknesses_json' => [],
            'next_actions_json' => [],
            'analyzed_at' => Carbon::parse($analyzedAt),
        ]);
    }

    private function seedCall(Organization $organization, string $createdAt, ConversationSource $source, CallProcessingStatus $status): void
    {
        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'source' => $source,
            'provider_code' => 'custom',
            'external_call_id' => uniqid('call-', true),
            'direction' => 'inbound',
            'caller_number' => '09120000000',
            'receiver_number' => '101',
            'status' => 'completed',
            'processing_status' => $status,
            'started_at' => Carbon::parse($createdAt),
        ]);

        $call->forceFill(['created_at' => Carbon::parse($createdAt)])->save();
    }
}
