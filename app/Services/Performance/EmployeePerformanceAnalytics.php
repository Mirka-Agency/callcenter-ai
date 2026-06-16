<?php

namespace App\Services\Performance;

use App\DTOs\ReportFilter;
use App\Support\JalaliDate;
use App\Models\ConversationAnalysis;
use App\Models\OrganizationUser;
use App\Services\Performance\Calculators\EmployeeMetricsCalculator;
use App\Services\Performance\Calculators\JsonFieldAggregator;
use App\Services\Performance\Calculators\PerformanceTrendCalculator;
use App\Services\Performance\Calculators\SentimentScoreCalculator;
use App\Services\Performance\Coaching\CoachingRecommendationBuilder;
use App\Services\Performance\Data\LoadedPerformanceData;
use App\Services\Performance\Data\PerformanceDataLoader;
use App\Services\Performance\Support\ProgressInsightFormatter;
use App\Services\Reports\CallMetricsAnalytics;
use App\Services\Reports\LeadConcernsAnalytics;
use Illuminate\Support\Facades\Cache;

class EmployeePerformanceAnalytics
{
    public function __construct(
        private PerformanceDataLoader $loader,
        private EmployeeMetricsCalculator $metricsCalculator,
        private PerformanceTrendCalculator $trendCalculator,
        private JsonFieldAggregator $jsonAggregator,
        private SentimentScoreCalculator $sentimentCalculator,
        private CoachingRecommendationBuilder $coachingBuilder,
        private ProgressInsightFormatter $insightFormatter,
        private LeadConcernsAnalytics $leadConcerns,
        private CallMetricsAnalytics $callMetrics,
        private PerformanceExecutiveSummaryService $summaryService,
    ) {}

    /** @return array<string, mixed> */
    public function teamDashboard(ReportFilter $filter): array
    {
        return Cache::remember(
            'performance:team:'.$filter->cacheKey(),
            120,
            fn () => $this->buildTeamDashboard($filter),
        );
    }

    /** @return array<string, mixed> */
    public function employeeProfile(ReportFilter $filter, OrganizationUser $employee): array
    {
        $employeeFilter = $this->scopedFilter($filter, $employee->id);

        return Cache::remember(
            'performance:employee:'.$employee->id.':'.$employeeFilter->cacheKey(),
            120,
            fn () => $this->buildEmployeeProfile($employeeFilter, $employee),
        );
    }

    /** @return array<string, mixed> */
    public function teamKpis(ReportFilter $filter): array
    {
        $data = $this->loader->load($filter, withPreviousPeriod: false);

        return $this->computeTeamKpis($filter, $data);
    }

    /** @return array<string, float|null> */
    public function teamKpiDeltas(ReportFilter $filter): array
    {
        $current = $this->loader->load($filter, withPreviousPeriod: false);
        $previous = $this->loader->load($filter->previousPeriod(), withPreviousPeriod: false);

        $currentKpis = $this->computeTeamKpis($filter, $current);
        $previousKpis = $this->computeTeamKpis($filter->previousPeriod(), $previous);

        return [
            'average_quality_score' => $this->percentDelta($currentKpis['average_quality_score'], $previousKpis['average_quality_score']),
            'average_lead_score' => $this->percentDelta($currentKpis['average_lead_score'], $previousKpis['average_lead_score']),
            'average_sentiment' => $this->percentDelta($currentKpis['average_sentiment'], $previousKpis['average_sentiment']),
            'total_calls' => $this->percentDelta($currentKpis['total_calls'], $previousKpis['total_calls']),
            'total_analyzed' => $this->percentDelta($currentKpis['total_analyzed'], $previousKpis['total_analyzed']),
        ];
    }

    /** @return list<array<int|string|null>> */
    public function exportTeamRows(ReportFilter $filter): array
    {
        $summaries = $this->buildEmployeeSummaries($this->loader->load($filter));

        return collect($summaries)->map(fn (array $row) => [
            $row['name'],
            $row['department'] ?? '—',
            $row['total_calls'],
            $row['total_analyzed'],
            $row['average_score'],
            $row['average_lead_score'],
            $row['average_sentiment'],
        ])->all();
    }

    /** @return list<array<int|string|null>> */
    public function exportEmployeeRows(ReportFilter $filter, OrganizationUser $employee): array
    {
        $profile = $this->employeeProfile($this->scopedFilter($filter, $employee->id), $employee);

        return collect($profile['recent_calls'])->map(fn (array $call) => [
            $call['date'],
            $call['customer'],
            $call['duration_label'],
            $call['quality_score'],
            $call['lead_score'] ?? '—',
            $call['sentiment'] ?? '—',
            $call['summary'] ?? '—',
        ])->all();
    }

    /** @return array<string, mixed> */
    private function buildTeamDashboard(ReportFilter $filter): array
    {
        $data = $this->loader->load($filter);
        $kpis = $this->computeTeamKpis($filter, $data);
        $deltas = $this->computeTeamKpiDeltas($filter, $data);
        $kpis['team_improvement_trend'] = $deltas['average_quality_score'];

        $summaries = $this->buildEmployeeSummaries($data);
        $rankings = $this->buildRankings($summaries);

        $dashboard = [
            'kpis' => $kpis,
            'kpis_delta' => $deltas,
            'employees' => $summaries,
            'rankings' => $rankings,
            'quality_trend' => $this->trendCalculator->qualityTrend($filter, $data->analyses),
            'lead_trend' => $this->trendCalculator->leadTrend($filter, $data->analyses),
            'volume_trend' => $this->trendCalculator->callVolumeTrend($filter, $data->calls),
            'sentiment_trend' => $this->trendCalculator->sentimentTrend($filter, $data->analyses),
            'quality_distribution' => $this->trendCalculator->qualityDistribution($data->analyses),
            'lead_distribution' => $this->leadConcerns->leadQualityDistribution($filter),
            'team_weaknesses' => $this->jsonAggregator->rankedItems($data->analyses, 'weaknesses_json'),
            'attention_employees' => $this->employeesRequiringAttention($summaries),
            'top_performers' => array_slice($rankings['best_quality'], 0, 3),
            'progress_insights' => $this->insightFormatter->teamInsights(
                $deltas,
                $rankings['most_improved'][0] ?? null,
            ),
        ];

        $dashboard['executive_summary'] = $this->summaryService->teamSummaryFromDashboard($filter, $dashboard);

        return $dashboard;
    }

    /** @return array<string, mixed> */
    private function buildEmployeeProfile(ReportFilter $filter, OrganizationUser $employee): array
    {
        $data = $this->loader->loadForEmployee($filter, $employee);
        $metrics = $this->metricsCalculator->compute($data->calls, $data->analyses);
        $deltas = $this->metricsCalculator->deltas(
            $metrics,
            $this->metricsCalculator->compute(
                $data->previousPeriod?->calls ?? collect(),
                $data->previousPeriod?->analyses ?? collect(),
            ),
        );

        $improvementAreas = $this->jsonAggregator->rankedImprovementAreas($data->analyses, 5);
        $weaknesses = collect($improvementAreas['items'])->pluck('item')->all();

        $profile = [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'avatar_url' => $employee->avatarUrl(),
                'department' => $employee->department,
                'position' => $employee->position,
            ],
            'metrics' => array_merge($metrics, [
                'sentiment_trend' => $deltas['sentiment_trend'],
            ]),
            'metrics_delta' => $deltas,
            'strengths' => $this->jsonAggregator->topItems($data->analyses, 'strengths_json'),
            'weaknesses' => $weaknesses,
            'improvement_areas' => array_slice($weaknesses, 0, 5),
            'coaching' => $this->coachingBuilder->build($weaknesses),
            'recent_calls' => $this->recentCallsWithRelations($filter, $employee),
            'quality_trend' => $this->trendCalculator->qualityTrend($filter, $data->analyses),
            'lead_trend' => $this->trendCalculator->leadTrend($filter, $data->analyses),
            'volume_trend' => $this->trendCalculator->callVolumeTrend($filter, $data->calls),
            'sentiment_trend' => $this->trendCalculator->sentimentTrend($filter, $data->analyses),
            'dimension_averages' => $this->averageDimensions($data->analyses),
            'progress_insights' => $this->insightFormatter->employeeInsights($deltas),
        ];

        $profile['executive_summary'] = $this->summaryService->employeeSummaryFromProfile($employee, $profile);

        return $profile;
    }

    /** @return list<array<string, mixed>> */
    private function recentCallsWithRelations(ReportFilter $filter, OrganizationUser $employee, int $limit = 15): array
    {
        return ConversationAnalysis::query()
            ->where('organization_id', $filter->organizationId)
            ->where('organization_user_id', $employee->id)
            ->whereBetween('analyzed_at', [$filter->from, $filter->to])
            ->with(['call:id,customer_id,customer_name,caller_number,duration_seconds', 'call.customer:id,name,company_name,phone_number,normalized_phone'])
            ->latest('analyzed_at')
            ->limit($limit)
            ->get(['id', 'call_id', 'score', 'summary', 'sentiment', 'lead_quality_json', 'analyzed_at'])
            ->map(function (ConversationAnalysis $analysis) {
                $call = $analysis->call;
                $lead = $analysis->lead_quality_json ?? [];

                return [
                    'analysis_id' => $analysis->id,
                    'call_id' => $call?->id,
                    'date' => JalaliDate::datetime($analysis->analyzed_at),
                    'customer' => $call?->customer?->displayName()
                        ?? $call?->customer_name
                        ?? $call?->caller_number
                        ?? '—',
                    'duration_seconds' => $call?->duration_seconds,
                    'duration_label' => $this->callMetrics->formatDuration($call?->duration_seconds ?? 0),
                    'quality_score' => $analysis->score,
                    'lead_score' => $lead['score'] ?? null,
                    'lead_level' => $lead['level'] ?? null,
                    'sentiment' => $analysis->sentiment?->label(),
                    'summary' => $analysis->summary,
                ];
            })
            ->all();
    }

    /** @return array<string, mixed> */
    private function computeTeamKpis(ReportFilter $filter, LoadedPerformanceData $data): array
    {
        $leadDist = $this->leadConcerns->leadQualityDistribution($filter);

        return [
            'total_employees' => OrganizationUser::query()
                ->where('organization_id', $filter->organizationId)
                ->count(),
            'active_employees' => $data->employees->count(),
            'total_calls' => $data->calls->count(),
            'total_analyzed' => $data->analyses->count(),
            'average_quality_score' => round((float) $data->analyses->avg('score'), 1),
            'average_lead_score' => $leadDist['average_score'],
            'average_sentiment' => $this->sentimentCalculator->average($data->analyses),
        ];
    }

    /** @return array<string, float|null> */
    private function computeTeamKpiDeltas(ReportFilter $filter, LoadedPerformanceData $data): array
    {
        $current = $this->computeTeamKpis($filter, $data);
        $previous = $this->computeTeamKpis(
            $filter->previousPeriod(),
            $data->previousPeriod ?? $this->loader->load($filter->previousPeriod(), withPreviousPeriod: false),
        );

        return [
            'average_quality_score' => $this->percentDelta($current['average_quality_score'], $previous['average_quality_score']),
            'average_lead_score' => $this->percentDelta($current['average_lead_score'], $previous['average_lead_score']),
            'average_sentiment' => $this->percentDelta($current['average_sentiment'], $previous['average_sentiment']),
            'total_calls' => $this->percentDelta($current['total_calls'], $previous['total_calls']),
            'total_analyzed' => $this->percentDelta($current['total_analyzed'], $previous['total_analyzed']),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildEmployeeSummaries(LoadedPerformanceData $data): array
    {
        $previous = $data->previousPeriod;

        return $data->employees->map(function (OrganizationUser $employee) use ($data, $previous) {
            $calls = $data->callsForEmployee($employee->id);
            $analyses = $data->analysesForEmployee($employee->id);
            $metrics = $this->metricsCalculator->compute($calls, $analyses);

            $prevMetrics = $previous
                ? $this->metricsCalculator->compute(
                    $previous->callsForEmployee($employee->id),
                    $previous->analysesForEmployee($employee->id),
                )
                : $this->emptyMetrics();

            $deltas = $this->metricsCalculator->deltas($metrics, $prevMetrics);

            $row = [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'avatar_url' => $employee->avatarUrl(),
                'department' => $employee->department,
                'position' => $employee->position,
                'average_score' => $metrics['average_quality_score'],
                'average_lead_score' => $metrics['average_lead_score'],
                'average_sentiment' => $metrics['average_sentiment'],
                'effectiveness_score' => $metrics['effectiveness_score'],
                'total_calls' => $metrics['total_calls'],
                'total_analyzed' => $metrics['total_analyzed'],
                'answered_calls' => $metrics['answered_calls'],
                'missed_calls' => $metrics['missed_calls'],
                'average_duration_label' => $metrics['average_duration_label'],
                'answer_rate' => $metrics['total_calls'] > 0
                    ? (int) round(($metrics['answered_calls'] / $metrics['total_calls']) * 100)
                    : null,
                'improvement_percent' => $deltas['quality_improvement_percent'],
                'trend' => $deltas['quality_trend'],
            ];

            return $row;
        })
            ->filter(fn (array $row) => $row['total_calls'] > 0 || $row['total_analyzed'] > 0)
            ->sortByDesc('average_score')
            ->values()
            ->map(function (array $row, int $index) {
                $row['rank'] = $index + 1;
                $row['tier'] = \App\Support\AgentPerformancePresenter::tier($row);

                return $row;
            })
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $summaries
     * @return array<string, list<array<string, mixed>>>
     */
    private function buildRankings(array $summaries): array
    {
        $employees = collect($summaries);

        return [
            'best_quality' => $employees->sortByDesc('average_score')->take(5)->values()->all(),
            'best_lead' => $employees->sortByDesc('average_lead_score')->take(5)->values()->all(),
            'most_improved' => $employees->sortByDesc('improvement_percent')->take(5)->values()->all(),
            'most_calls' => $employees->sortByDesc('total_calls')->take(5)->values()->all(),
            'best_sentiment' => $employees->sortByDesc('average_sentiment')->take(5)->values()->all(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $summaries
     * @return list<array<string, mixed>>
     */
    private function employeesRequiringAttention(array $summaries): array
    {
        return collect($summaries)
            ->filter(fn (array $row) => $row['average_score'] < 60 || $row['trend'] === 'declining')
            ->sortBy('average_score')
            ->take(5)
            ->values()
            ->all();
    }

    /** @param  \Illuminate\Support\Collection<int, ConversationAnalysis>  $analyses */
    private function averageDimensions(\Illuminate\Support\Collection $analyses): array
    {
        $sums = [];
        $counts = [];

        foreach ($analyses as $analysis) {
            foreach ($analysis->performance_dimensions_json ?? [] as $key => $value) {
                $score = is_array($value) ? (int) ($value['score'] ?? 0) : (int) $value;
                $sums[$key] = ($sums[$key] ?? 0) + $score;
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        return collect($sums)
            ->map(fn (int $sum, string $key) => round($sum / $counts[$key], 1))
            ->all();
    }

    /** @return array<string, mixed> */
    private function emptyMetrics(): array
    {
        return [
            'average_quality_score' => 0.0,
            'average_lead_score' => 0.0,
            'average_sentiment' => 0.0,
        ];
    }

    private function scopedFilter(ReportFilter $filter, int $employeeId): ReportFilter
    {
        return new ReportFilter(
            organizationId: $filter->organizationId,
            preset: $filter->preset,
            from: $filter->from,
            to: $filter->to,
            employeeIds: [$employeeId],
            compareMode: $filter->compareMode,
        );
    }

    private function percentDelta(float|int|null $current, float|int|null $previous): ?float
    {
        if ($current === null || $previous === null) {
            return null;
        }

        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
