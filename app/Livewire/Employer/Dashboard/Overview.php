<?php

namespace App\Livewire\Employer\Dashboard;

use App\DTOs\ReportFilter;
use App\Enums\ReportDatePreset;
use App\Livewire\Employer\Concerns\HasAgentPerformanceCardFeed;
use App\Livewire\Employer\Concerns\HasQualityTrendDrilldown;
use App\Livewire\Employer\Concerns\HasTeamWeaknessDrilldown;
use App\Services\Demo\DemoAnalyticsClock;
use App\Services\EmployerContext;
use App\Services\EmployerDashboardAnalytics;
use App\Services\Performance\EmployeePerformanceAnalytics;
use App\Services\Reports\OrganizationCallMetrics;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employer')]
#[Title('داشبورد مدیر')]
class Overview extends Component
{
    use HasAgentPerformanceCardFeed;
    use HasQualityTrendDrilldown;
    use HasTeamWeaknessDrilldown;

    public function render()
    {
        $organization = EmployerContext::organization();
        $organizationId = $organization->id;

        if ($organization->is_demo) {
            app(DemoAnalyticsClock::class)->refreshIfStale($organization);
        }

        $analytics = EmployerDashboardAnalytics::forOrganization($organizationId);

        $performanceFilter = ReportFilter::make(
            organizationId: $organizationId,
            preset: ReportDatePreset::Last30,
        );
        $performance = app(EmployeePerformanceAnalytics::class);
        $performanceDashboard = $performance->teamDashboard($performanceFilter);
        $weekComparisons = $this->weekComparisons(
            $performance->teamKpiPointDeltas(ReportFilter::make(
                organizationId: $organizationId,
                preset: ReportDatePreset::Last7,
            )),
        );
        $selectedWeakness = $this->resolvedTeamWeakness($performanceDashboard['team_weaknesses']);

        $agents = $performanceDashboard['employees'];
        $selectedQualityPeriod = $this->resolvedQualityTrendPeriod($performanceDashboard['quality_trend']);

        return view('livewire.employer.dashboard.overview', [
            'organization' => $organization,
            'cockpit' => [
                'calls_today' => app(OrganizationCallMetrics::class)->countToday($organizationId),
            ],
            'agentCardFeed' => $this->agentCardFeed($agents),
            'teamKpis' => $performanceDashboard['kpis'],
            'weekComparisons' => $weekComparisons,
            'teamWeaknesses' => $performanceDashboard['team_weaknesses'],
            'selectedTeamWeakness' => $selectedWeakness,
            'teamWeaknessCalls' => $selectedWeakness
                ? $performance->teamWeaknessCalls($performanceFilter, $selectedWeakness)
                : [],
            'tradingOpportunities' => $analytics->tradingOpportunities(),
            'sentimentCustomers' => $analytics->sentimentCustomers(),
            'forgottenFollowUps' => $analytics->forgottenFollowUps(),
            'qualityTrend' => $performanceDashboard['quality_trend'],
            'qualityTrendInsights' => $performanceDashboard['quality_trend_insights'] ?? [],
            'qualityTrendInsight' => $selectedQualityPeriod
                ? ($performanceDashboard['quality_trend_insights'][$selectedQualityPeriod] ?? null)
                : null,
            'agentProfileBase' => preg_replace('#/\d+$#', '', route('employer.intelligence.performance.show', 1)),
        ]);
    }

    /**
     * @param  array<string, float|null>  $deltas
     * @return array{average_quality_score: ?float, average_lead_score: ?float, average_sentiment: ?float}
     */
    private function weekComparisons(array $deltas): array
    {
        return [
            'average_quality_score' => $deltas['average_quality_score'] ?? null,
            'average_lead_score' => $deltas['average_lead_score'] ?? null,
            'average_sentiment' => $deltas['average_sentiment'] ?? null,
        ];
    }
}
