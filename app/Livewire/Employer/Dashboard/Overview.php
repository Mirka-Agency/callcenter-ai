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
        $monthComparisons = $this->monthComparisons(
            $performance->teamKpiPointDeltas($performanceFilter),
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
            'monthComparisons' => $monthComparisons,
            'teamWeaknesses' => $performanceDashboard['team_weaknesses'],
            'selectedTeamWeakness' => $selectedWeakness,
            'teamWeaknessCalls' => $selectedWeakness
                ? $performance->teamWeaknessCalls($performanceFilter, $selectedWeakness)
                : [],
            'tradingOpportunities' => array_slice($analytics->tradingOpportunities(), 0, 30),
            'sentimentCustomers' => $analytics->sentimentCustomers(),
            'forgottenFollowUps' => array_slice($analytics->forgottenFollowUps(), 0, 40),
            'qualityTrend' => $performanceDashboard['quality_trend'],
            'qualityTrendInsights' => $performanceDashboard['quality_trend_insights'] ?? [],
            'qualityTrendInsight' => $selectedQualityPeriod
                ? ($performanceDashboard['quality_trend_insights'][$selectedQualityPeriod] ?? null)
                : null,
            'agentProfileBase' => preg_replace('#/\d+$#', '', route('employer.intelligence.performance.show', 1)),
            'progressAgentCalls' => $this->progressAgents($performanceDashboard['attention_employees']),
        ]);
    }

    /**
     * Agents whose repeated weaknesses show they need to improve.
     *
     * @param  list<array<string, mixed>>  $agents
     * @return list<array{name: string, url: string}>
     */
    private function progressAgents(array $agents): array
    {
        return collect($agents)
            ->map(fn (array $agent): array => [
                'name' => $agent['name'] ?? '—',
                'url' => route('employer.intelligence.performance.show', $agent['id']),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, float|null>  $deltas
     * @return array{average_quality_score: ?float, average_lead_score: ?float, average_sentiment: ?float}
     */
    private function monthComparisons(array $deltas): array
    {
        return [
            'average_quality_score' => $deltas['average_quality_score'] ?? null,
            'average_lead_score' => $deltas['average_lead_score'] ?? null,
            'average_sentiment' => $deltas['average_sentiment'] ?? null,
        ];
    }
}
