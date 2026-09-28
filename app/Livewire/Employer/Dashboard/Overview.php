<?php

namespace App\Livewire\Employer\Dashboard;

use App\DTOs\ReportFilter;
use App\Enums\ReportDatePreset;
use App\Livewire\Employer\Concerns\HasAgentPerformanceCardFeed;
use App\Livewire\Employer\Concerns\HasQualityTrendDrilldown;
use App\Livewire\Employer\Concerns\HasTeamWeaknessDrilldown;
use App\Models\ConversationAnalysis;
use App\Services\Demo\DemoAnalyticsClock;
use App\Services\EmployerContext;
use App\Services\EmployerDashboardAnalytics;
use App\Services\Performance\EmployeePerformanceAnalytics;
use App\Services\Reports\OrganizationCallMetrics;
use Illuminate\Support\Facades\DB;
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
            'tradingOpportunities' => array_slice($analytics->tradingOpportunities(), 0, 30),
            'sentimentCustomers' => $analytics->sentimentCustomers(),
            'forgottenFollowUps' => array_slice($analytics->forgottenFollowUps(), 0, 40),
            'qualityTrend' => $performanceDashboard['quality_trend'],
            'qualityTrendInsights' => $performanceDashboard['quality_trend_insights'] ?? [],
            'qualityTrendInsight' => $selectedQualityPeriod
                ? ($performanceDashboard['quality_trend_insights'][$selectedQualityPeriod] ?? null)
                : null,
            'agentProfileBase' => preg_replace('#/\d+$#', '', route('employer.intelligence.performance.show', 1)),
            'progressAgentCalls' => $this->progressAgentCalls($agents),
        ]);
    }

    /**
     * Latest analyzed call for each agent who needs to improve.
     *
     * @param  list<array<string, mixed>>  $agents
     * @return list<array{name: string, url: string}>
     */
    private function progressAgentCalls(array $agents): array
    {
        $attention = collect($agents)->where('tier', 'attention')->values();

        if ($attention->isEmpty()) {
            return [];
        }

        $employeeIds = $attention->pluck('id')->all();
        $latestPerEmployee = ConversationAnalysis::query()
            ->selectRaw('organization_user_id, MAX(analyzed_at) as analyzed_at')
            ->whereIn('organization_user_id', $employeeIds)
            ->where(function ($evaluable): void {
                $evaluable->where('is_evaluable', true)->orWhereNull('is_evaluable');
            })
            ->where('score', '>', 0)
            ->groupBy('organization_user_id');
        $latestIds = DB::query()
            ->from('conversation_analyses as analyses')
            ->joinSub($latestPerEmployee, 'latest', function ($join): void {
                $join->on('analyses.organization_user_id', '=', 'latest.organization_user_id')
                    ->on('analyses.analyzed_at', '=', 'latest.analyzed_at');
            })
            ->where(function ($evaluable): void {
                $evaluable->where('analyses.is_evaluable', true)->orWhereNull('analyses.is_evaluable');
            })
            ->where('analyses.score', '>', 0)
            ->groupBy('analyses.organization_user_id')
            ->selectRaw('MAX(analyses.id) as id')
            ->pluck('id');
        $latest = ConversationAnalysis::query()
            ->whereIn('id', $latestIds)
            ->with([
                'call:id,customer_id,customer_name,customer_phone,caller_number',
                'call.customer:id,name,company_name,phone_number',
            ])
            ->get()
            ->keyBy('organization_user_id');

        return $attention->map(function (array $agent) use ($latest): array {
            $analysis = $latest->get($agent['id']);
            $call = $analysis?->call;
            $customer = $call?->customer;
            $name = $customer?->displayName()
                ?: ($call?->customer_name ?: null)
                ?: ($customer?->phone_number ?: null)
                ?: ($call?->customer_phone ?: null)
                ?: ($call?->caller_number ?: null)
                ?: ($agent['name'] ?? '—');

            return [
                'name' => $name,
                'url' => $analysis
                    ? route('employer.intelligence.show', $analysis->id)
                    : route('employer.intelligence.performance.show', $agent['id']),
            ];
        })->all();
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
