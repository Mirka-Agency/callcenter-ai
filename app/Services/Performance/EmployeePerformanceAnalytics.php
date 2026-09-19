<?php

namespace App\Services\Performance;

use App\DTOs\ReportFilter;
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
use App\Support\AgentPerformancePresenter;
use App\Support\JalaliDate;
use Illuminate\Support\Collection;
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

    /**
     * @return list<array<string, mixed>>
     */
    public function teamWeaknessCalls(ReportFilter $filter, ?string $weakness, int $limit = 20): array
    {
        $weakness = is_string($weakness) ? trim($weakness) : '';

        if ($weakness === '' || mb_strlen($weakness) > 500) {
            return [];
        }

        $data = $this->loader->load($filter, withPreviousPeriod: false);
        $allowed = collect($this->jsonAggregator->rankedItems($data->analyses, 'weaknesses_json'))
            ->pluck('item')
            ->all();

        if (! in_array($weakness, $allowed, true)) {
            return [];
        }

        $matchingIds = $data->analyses
            ->filter(fn (ConversationAnalysis $analysis) => $this->jsonAggregator->analysisHasItem($analysis, 'weaknesses_json', $weakness))
            ->pluck('id')
            ->all();

        if ($matchingIds === []) {
            return [];
        }

        return ConversationAnalysis::query()
            ->where('organization_id', $filter->organizationId)
            ->whereIn('id', $matchingIds)
            ->with([
                'employee.user:id,avatar_path,name',
                'call:id,customer_id,customer_name,caller_number,duration_seconds',
                'call.customer:id,name,company_name,phone_number,normalized_phone',
            ])
            ->latest('analyzed_at')
            ->limit($limit)
            ->get(['id', 'call_id', 'organization_user_id', 'score', 'is_evaluable', 'summary', 'sentiment', 'lead_quality_json', 'analyzed_at'])
            ->map(function (ConversationAnalysis $analysis) {
                $call = $analysis->call;
                $lead = $analysis->lead_quality_json ?? [];

                return [
                    'analysis_id' => $analysis->id,
                    'call_id' => $call?->id,
                    'date' => JalaliDate::datetime($analysis->analyzed_at),
                    'employee' => $analysis->employee?->full_name ?? '—',
                    'customer' => $call?->customer?->displayName()
                        ?? $call?->customer_name
                        ?? $call?->caller_number
                        ?? '—',
                    'duration_label' => $this->callMetrics->formatDuration($call?->duration_seconds ?? 0),
                    'quality_score' => $analysis->isEvaluable() ? $analysis->score : null,
                    'lead_score' => $analysis->isEvaluable() ? ($lead['score'] ?? null) : null,
                    'sentiment' => $analysis->sentiment?->label(),
                    'summary' => $analysis->summary,
                ];
            })
            ->all();
    }

    /**
     * Explains why the team quality trend moved at a clicked chart point.
     *
     * @return array{
     *     period: string,
     *     label: string,
     *     direction: 'up'|'down'|'stable'|'baseline',
     *     headline: string,
     *     reason: string,
     *     current_score: float,
     *     previous_score: ?float,
     *     score_delta: ?float,
     *     analyzed_count: int,
     *     factors: list<array{item: string, count: int}>,
     *     agents: list<array{
     *         id: int,
     *         name: string,
     *         avatar_url: ?string,
     *         score: float,
     *         previous_score: ?float,
     *         score_delta: ?float,
     *         analyzed_count: int,
     *         contribution: float,
     *         highlight: ?string
     *     }>
     * }|null
     */
    public function qualityTrendPointInsight(ReportFilter $filter, ?string $period): ?array
    {
        $period = is_string($period) ? trim($period) : '';

        if ($period === '' || mb_strlen($period) > 32 || ! preg_match('/^\d{4}-\d{2}(?:-\d{2})?$/', $period)) {
            return null;
        }

        return $this->teamDashboard($filter)['quality_trend_insights'][$period] ?? null;
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
            'attention_employees' => $this->employeesRequiringAttention($summaries, $data),
            'top_performers' => array_slice($rankings['best_quality'], 0, 3),
            'progress_insights' => $this->insightFormatter->teamInsights(
                $deltas,
                $rankings['most_improved'][0] ?? null,
            ),
        ];

        $dashboard['quality_trend_insights'] = $this->buildQualityTrendPointInsights(
            $filter,
            $data,
            $dashboard['quality_trend'],
        );

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

        $employee->loadMissing('user');

        $profile = [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'avatar_url' => $employee->avatarUrl(),
                'department' => $employee->department,
                'position' => $employee->position,
                'email' => $employee->user?->email,
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
            ->get(['id', 'call_id', 'score', 'is_evaluable', 'summary', 'sentiment', 'lead_quality_json', 'analyzed_at'])
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
                    'quality_score' => $analysis->isEvaluable() ? $analysis->score : null,
                    'lead_score' => $analysis->isEvaluable() ? ($lead['score'] ?? null) : null,
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

        $scored = $data->analyses->filter(fn ($analysis) => $analysis->isEvaluable());

        return [
            'total_employees' => OrganizationUser::query()
                ->where('organization_id', $filter->organizationId)
                ->count(),
            'active_employees' => $data->employees->count(),
            'total_calls' => $data->calls->count(),
            'total_analyzed' => $data->analyses->count(),
            'average_quality_score' => $scored->isNotEmpty() ? round((float) $scored->avg('score'), 1) : 0.0,
            'average_lead_score' => $leadDist['average_score'],
            'average_sentiment' => $this->sentimentCalculator->average($scored),
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
                $row['tier'] = AgentPerformancePresenter::tier($row);

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
     * Agents whose analyses show several weaknesses, with at least one pattern repeating across calls.
     *
     * @param  list<array<string, mixed>>  $summaries
     * @return list<array<string, mixed>>
     */
    private function employeesRequiringAttention(array $summaries, LoadedPerformanceData $data): array
    {
        return collect($summaries)
            ->map(function (array $row) use ($data) {
                if ((int) ($row['total_analyzed'] ?? 0) < 2) {
                    return null;
                }

                $weaknesses = $this->jsonAggregator->rankedItems(
                    $data->analysesForEmployee((int) $row['id']),
                    'weaknesses_json',
                    12,
                );

                $repeated = collect($weaknesses)
                    ->filter(fn (array $item) => (int) ($item['count'] ?? 0) >= 2)
                    ->values()
                    ->all();

                $analyzed = max(1, (int) $row['total_analyzed']);
                $repeatedOccurrences = (int) collect($repeated)->sum('count');
                $weaknessRate = $repeatedOccurrences / $analyzed;

                if (! $this->hasRepeatedCoachingWeaknesses($repeated) || $weaknessRate <= 0.5) {
                    return null;
                }

                return array_merge($row, [
                    'repeated_weaknesses' => array_slice($repeated, 0, 3),
                    'distinct_weakness_count' => count($weaknesses),
                    'repeated_weakness_count' => count($repeated),
                    'repeated_weakness_occurrences' => $repeatedOccurrences,
                    'weakness_rate' => round($weaknessRate, 2),
                ]);
            })
            ->filter()
            ->sortBy([
                ['weakness_rate', 'desc'],
                ['repeated_weakness_occurrences', 'desc'],
                ['average_score', 'asc'],
            ])
            ->take(6)
            ->values()
            ->all();
    }

    /**
     * @param  list<array{item: string, count: int}>  $repeated
     */
    private function hasRepeatedCoachingWeaknesses(array $repeated): bool
    {
        if ($repeated === []) {
            return false;
        }

        if (count($repeated) >= 2) {
            return true;
        }

        return (int) ($repeated[0]['count'] ?? 0) >= 3;
    }

    /** @param  Collection<int, ConversationAnalysis>  $analyses */
    private function averageDimensions(Collection $analyses): array
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

    /**
     * @param  list<array{period: string, label: string, avg_score?: float}>  $trend
     * @return array<string, array<string, mixed>>
     */
    private function buildQualityTrendPointInsights(ReportFilter $filter, LoadedPerformanceData $data, array $trend): array
    {
        $insights = [];

        foreach ($trend as $row) {
            $period = (string) ($row['period'] ?? '');

            if ($period === '') {
                continue;
            }

            $insights[$period] = $this->insightForTrendRow($filter, $data, $trend, $row);
        }

        return $insights;
    }

    /**
     * @param  list<array{period: string, label: string, avg_score?: float}>  $trend
     * @param  array{period: string, label: string, avg_score?: float}  $currentRow
     * @return array<string, mixed>
     */
    private function insightForTrendRow(
        ReportFilter $filter,
        LoadedPerformanceData $data,
        array $trend,
        array $currentRow,
    ): array {
        $period = (string) $currentRow['period'];
        $previousRow = $this->trendCalculator->previousTrendRow($trend, $period);
        $currentAnalyses = $this->trendCalculator->analysesForPeriod($filter, $data->analyses, $period);
        $previousAnalyses = is_array($previousRow)
            ? $this->trendCalculator->analysesForPeriod($filter, $data->analyses, $previousRow['period'])
            : collect();

        $currentScore = (float) ($currentRow['avg_score'] ?? 0);
        $previousScore = is_array($previousRow) ? (float) ($previousRow['avg_score'] ?? 0) : null;
        $direction = $this->trendDirection($currentScore, $previousScore);
        $agents = $this->trendPointAgents(
            $data->employees,
            $currentAnalyses,
            $previousAnalyses,
            $previousScore ?? $currentScore,
            $direction,
        );
        $factors = $this->trendPointFactors($currentAnalyses, $agents, $direction);
        $label = (string) ($currentRow['label'] ?? $period);

        return [
            'period' => $period,
            'label' => $label,
            'direction' => $direction,
            'headline' => $this->trendHeadline($direction),
            'reason' => $this->trendReason($direction, $label, $factors, $currentScore),
            'current_score' => $currentScore,
            'previous_score' => $previousScore,
            'score_delta' => $previousScore === null ? null : round($currentScore - $previousScore, 1),
            'analyzed_count' => $currentAnalyses->count(),
            'factors' => $factors,
            'agents' => $agents,
        ];
    }

    /**
     * @return 'up'|'down'|'stable'|'baseline'
     */
    private function trendDirection(float $currentScore, ?float $previousScore): string
    {
        if ($previousScore === null) {
            return 'baseline';
        }

        $delta = round($currentScore - $previousScore, 1);

        if ($delta > 0) {
            return 'up';
        }

        if ($delta < 0) {
            return 'down';
        }

        return 'stable';
    }

    private function trendHeadline(string $direction): string
    {
        return match ($direction) {
            'up' => 'افزایش کیفیت',
            'down' => 'کاهش کیفیت',
            'stable' => 'کیفیت پایدار',
            default => 'کیفیت این روز',
        };
    }

    /**
     * @param  list<array{item: string, count: int}>  $factors
     */
    private function trendReason(
        string $direction,
        string $label,
        array $factors,
        float $currentScore,
    ): string {
        $joined = collect($factors)
            ->pluck('item')
            ->filter(fn ($item) => is_string($item) && trim($item) !== '')
            ->take(2)
            ->implode(' و ');

        return match ($direction) {
            'up' => $joined !== ''
                ? "روند کیفیت در {$label} افزایش داشت، به این دلیل که {$joined}."
                : "روند کیفیت در {$label} افزایش داشت، به این دلیل که میانگین امتیاز کارشناسان نسبت به نقطه قبل بالاتر رفت.",
            'down' => $joined !== ''
                ? "روند کیفیت در {$label} کاهش داشت، به این دلیل که {$joined}."
                : "روند کیفیت در {$label} کاهش داشت، به این دلیل که میانگین امتیاز کارشناسان نسبت به نقطه قبل پایین‌تر آمد.",
            'stable' => "روند کیفیت در {$label} نسبت به نقطه قبل تقریباً ثابت ماند.",
            default => "کیفیت تیم در {$label} برابر {$currentScore} بوده است.",
        };
    }

    /**
     * @param  Collection<int, OrganizationUser>  $employees
     * @param  Collection<int, ConversationAnalysis>  $currentAnalyses
     * @param  Collection<int, ConversationAnalysis>  $previousAnalyses
     * @param  'up'|'down'|'stable'|'baseline'  $direction
     * @return list<array{
     *     id: int,
     *     name: string,
     *     avatar_url: ?string,
     *     score: float,
     *     previous_score: ?float,
     *     score_delta: ?float,
     *     analyzed_count: int,
     *     contribution: float,
     *     highlight: ?string
     * }>
     */
    private function trendPointAgents(
        Collection $employees,
        Collection $currentAnalyses,
        Collection $previousAnalyses,
        float $previousTeamAvg,
        string $direction,
    ): array {
        $currentScored = $currentAnalyses->filter(fn (ConversationAnalysis $analysis) => $analysis->isEvaluable());
        $previousScored = $previousAnalyses->filter(fn (ConversationAnalysis $analysis) => $analysis->isEvaluable());
        $totalCurrent = $currentScored->count();

        if ($totalCurrent === 0) {
            return [];
        }

        $employeesById = $employees->keyBy('id');
        $previousByEmployee = $previousScored->groupBy('organization_user_id');
        $highlightColumn = $direction === 'down' ? 'weaknesses_json' : 'strengths_json';

        $agents = $currentScored
            ->groupBy('organization_user_id')
            ->map(function (Collection $items, $employeeId) use (
                $employeesById,
                $previousByEmployee,
                $previousTeamAvg,
                $totalCurrent,
                $highlightColumn,
            ) {
                $employee = $employeesById->get((int) $employeeId);
                $score = round((float) $items->avg('score'), 1);
                $previousItems = $previousByEmployee->get($employeeId, collect());
                $previousScore = $previousItems->isNotEmpty()
                    ? round((float) $previousItems->avg('score'), 1)
                    : null;
                $personalDelta = $previousScore !== null
                    ? round($score - $previousScore, 1)
                    : round($score - $previousTeamAvg, 1);

                return [
                    'id' => (int) $employeeId,
                    'name' => $employee?->full_name ?: '—',
                    'avatar_url' => $employee?->avatarUrl(),
                    'score' => $score,
                    'previous_score' => $previousScore,
                    'score_delta' => $previousScore === null ? null : round($score - $previousScore, 1),
                    'analyzed_count' => $items->count(),
                    'contribution' => round($personalDelta * ($items->count() / $totalCurrent), 2),
                    'highlight' => $this->jsonAggregator->topItems($items, $highlightColumn, 1)[0] ?? null,
                ];
            });

        $filtered = $this->filterTrendPointAgents($agents, $direction);

        if ($filtered->isEmpty()) {
            $filtered = $agents->sortByDesc('analyzed_count');
        }

        return $filtered->take(6)->values()->all();
    }

    /**
     * @param  Collection<int, array{score_delta: ?float, contribution: float, analyzed_count: int}>  $agents
     * @param  'up'|'down'|'stable'|'baseline'  $direction
     * @return Collection<int, array<string, mixed>>
     */
    private function filterTrendPointAgents(Collection $agents, string $direction): Collection
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            return $agents->sortByDesc('analyzed_count');
        }

        $delta = fn (array $agent) => (float) ($agent['score_delta'] ?? $agent['contribution']);
        $maxMagnitude = (float) $agents->max(fn (array $agent) => abs($delta($agent)));
        $threshold = max(2.0, round($maxMagnitude * 0.25, 1));

        return $direction === 'up'
            ? $agents->filter(fn (array $agent) => $delta($agent) >= $threshold)->sortByDesc($delta)
            : $agents->filter(fn (array $agent) => $delta($agent) <= -$threshold)->sortBy($delta);
    }

    /**
     * @param  Collection<int, ConversationAnalysis>  $currentAnalyses
     * @param  list<array{id: int}>  $agents
     * @param  'up'|'down'|'stable'|'baseline'  $direction
     * @return list<array{item: string, count: int}>
     */
    private function trendPointFactors(Collection $currentAnalyses, array $agents, string $direction): array
    {
        $column = $direction === 'down' ? 'weaknesses_json' : 'strengths_json';
        $contributorIds = collect($agents)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $source = $currentAnalyses->filter(
            fn (ConversationAnalysis $analysis) => in_array((int) $analysis->organization_user_id, $contributorIds, true),
        );

        if ($source->isEmpty()) {
            $source = $currentAnalyses;
        }

        return $this->jsonAggregator->rankedItems($source, $column, 3);
    }
}
