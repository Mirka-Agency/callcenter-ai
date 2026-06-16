<?php

namespace App\Services\Reports;

use App\DTOs\ReportFilter;
use App\Models\ConversationAnalysis;
use App\Models\OrganizationUser;
use App\Models\PlatformAiSettings;
use App\Services\AiPerformanceAnalytics;
use App\Support\JalaliDate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class EmployerReportsAnalytics
{
    public function __construct(
        private LeadConcernsAnalytics $leadConcerns,
        private CallMetricsAnalytics $callMetrics,
    ) {}

    public function dashboard(ReportFilter $filter): array
    {
        $dashboard = Cache::remember(
            'employer_reports:v2:'.$filter->cacheKey(),
            120,
            fn () => [
                'kpis' => $this->kpis($filter),
                'kpis_delta' => $this->kpiDeltas($filter),
                'call_activity_trend' => $this->callMetrics->callActivityTrend($filter),
                'quality_trend' => $this->qualityTrend($filter),
                'lead_distribution' => $this->leadConcerns->leadQualityDistribution($filter),
                'concerns_breakdown' => $this->leadConcerns->concernsByType($filter),
                'employee_comparison' => $this->employeeComparison($filter),
                'leaderboards' => $this->leaderboards($filter),
                'ai_usage_trend' => $this->aiUsageTrend($filter),
                'executive_summary' => app(ReportExecutiveSummaryService::class)->generate($filter, $this, false),
            ],
        );

        return $this->withEmployeeAvatars($dashboard);
    }

    /** @param  array<string, mixed>  $dashboard */
    private function withEmployeeAvatars(array $dashboard): array
    {
        $ids = collect($dashboard['leaderboards'] ?? [])
            ->flatten(1)
            ->pluck('id')
            ->merge(collect($dashboard['employee_comparison'] ?? [])->pluck('id'))
            ->unique()
            ->filter()
            ->values();

        if ($ids->isEmpty()) {
            return $dashboard;
        }

        $avatars = OrganizationUser::query()
            ->whereIn('id', $ids)
            ->with('user:id,avatar_path,name')
            ->get()
            ->mapWithKeys(fn (OrganizationUser $employee) => [
                $employee->id => $employee->avatarUrl(),
            ]);

        $applyAvatars = fn (array $row) => array_merge($row, [
            'avatar_url' => $avatars[$row['id']] ?? $row['avatar_url'] ?? null,
        ]);

        $dashboard['employee_comparison'] = collect($dashboard['employee_comparison'] ?? [])
            ->map($applyAvatars)
            ->values()
            ->all();

        $dashboard['leaderboards'] = collect($dashboard['leaderboards'] ?? [])
            ->map(fn ($board) => collect($board)->map($applyAvatars)->values()->all())
            ->all();

        return $dashboard;
    }

    public function executiveSummary(ReportFilter $filter): string
    {
        return app(ReportExecutiveSummaryService::class)->generate($filter, $this, false);
    }

    /** @return array<string, mixed> */
    public function kpis(ReportFilter $filter): array
    {
        $query = $filter->applyToAnalysisQuery(ConversationAnalysis::query());
        $totalAnalyzed = (clone $query)->count();
        $avgScore = round((float) (clone $query)->avg('score'), 1);
        $totalCost = round((float) (clone $query)->sum('cost'), 4);
        $totalTokens = (int) (clone $query)->sum('total_tokens');

        $leadDist = $this->leadConcerns->leadQualityDistribution($filter);
        $topEmployee = $this->leaderboards($filter)['overall'][0] ?? null;

        return [
            'total_calls' => $this->callMetrics->totalCalls($filter),
            'total_analyzed' => $totalAnalyzed,
            'average_quality_score' => $avgScore,
            'average_lead_quality_score' => $leadDist['average_score'],
            'total_leads' => $leadDist['total'],
            'high_quality_leads' => $leadDist['high'],
            'total_concerns' => $this->leadConcerns->totalConcerns($filter),
            'average_call_duration' => $this->callMetrics->averageCallDurationSeconds($filter),
            'average_call_duration_label' => $this->callMetrics->formatDuration(
                $this->callMetrics->averageCallDurationSeconds($filter),
            ),
            'total_ai_cost' => PlatformAiSettings::formatMoney($totalCost),
            'total_ai_cost_raw' => $totalCost,
            'total_tokens' => $totalTokens,
            'top_employee' => $topEmployee['name'] ?? '—',
            'top_employee_score' => $topEmployee['composite_score'] ?? 0,
        ];
    }

    /** @return array<string, float|null> */
    public function kpiDeltas(ReportFilter $filter): array
    {
        $previous = $filter->previousPeriod();
        $current = $this->kpis($filter);
        $prev = $this->kpis($previous);

        return [
            'average_quality_score' => $this->delta($current['average_quality_score'], $prev['average_quality_score']),
            'average_lead_quality_score' => $this->delta($current['average_lead_quality_score'], $prev['average_lead_quality_score']),
            'total_analyzed' => $this->delta($current['total_analyzed'], $prev['total_analyzed']),
            'high_quality_leads' => $this->delta($current['high_quality_leads'], $prev['high_quality_leads']),
        ];
    }

    /** @return list<array{period: string, label: string, avg_score: float, count: int}> */
    public function qualityTrend(ReportFilter $filter): array
    {
        $granularity = $filter->granularity();
        $query = $filter->applyToAnalysisQuery(ConversationAnalysis::query())
            ->whereNotNull('analyzed_at')
            ->orderBy('analyzed_at');

        $grouped = $query->get(['analyzed_at', 'score'])->groupBy(function (ConversationAnalysis $analysis) use ($granularity) {
            return match ($granularity) {
                'week' => $analysis->analyzed_at->format('Y-W'),
                default => $analysis->analyzed_at->format('Y-m-d'),
            };
        });

        return $grouped->map(function (Collection $items, string $key) use ($granularity) {
            return [
                'period' => $key,
                'label' => $granularity === 'week' ? 'هفته '.$key : JalaliDate::monthDay($key),
                'avg_score' => round((float) $items->avg('score'), 1),
                'count' => $items->count(),
            ];
        })->values()->all();
    }

    /** @return list<array{id: int, name: string, average_score: float, average_lead_score: float, total_analyzed: int, high_leads: int}> */
    public function employeeComparison(ReportFilter $filter): array
    {
        $performance = AiPerformanceAnalytics::forOrganization($filter->organizationId)
            ->employeePerformanceInRange($filter);

        $leadByEmployee = collect($this->leadConcerns->employeeLeadQuality($filter))->keyBy('id');

        return $performance->map(function (array $employee) use ($leadByEmployee) {
            $lead = $leadByEmployee->get($employee['id'], []);

            return [
                'id' => $employee['id'],
                'name' => $employee['name'],
                'avatar_url' => $employee['avatar_url'] ?? null,
                'average_score' => $employee['average_score'],
                'average_lead_score' => $lead['average_lead_score'] ?? 0,
                'total_analyzed' => $employee['total_analyzed'],
                'high_leads' => $lead['high_leads'] ?? 0,
            ];
        })->sortByDesc('average_score')->values()->all();
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function leaderboards(ReportFilter $filter): array
    {
        $employees = $this->employeeComparison($filter);

        return [
            'best_quality' => collect($employees)->sortByDesc('average_score')->take(5)->values()->all(),
            'most_analyzed' => collect($employees)->sortByDesc('total_analyzed')->take(5)->values()->all(),
            'highest_lead' => collect($employees)->sortByDesc('average_lead_score')->take(5)->values()->all(),
            'overall' => collect($employees)->map(function (array $row) {
                $volumeScore = min(100, $row['total_analyzed'] * 5);
                $composite = round(
                    ($row['average_score'] * 0.4)
                    + ($row['average_lead_score'] * 0.3)
                    + ($volumeScore * 0.2)
                    + ($row['high_leads'] * 2 * 0.1),
                    1,
                );

                return array_merge($row, ['composite_score' => min(100, $composite)]);
            })->sortByDesc('composite_score')->take(5)->values()->all(),
        ];
    }

    /** @return list<array{period: string, label: string, analyses: int, tokens: int, cost: float}> */
    public function aiUsageTrend(ReportFilter $filter): array
    {
        $query = $filter->applyToAnalysisQuery(ConversationAnalysis::query())
            ->whereNotNull('analyzed_at')
            ->orderBy('analyzed_at');

        $grouped = $query->get(['analyzed_at', 'total_tokens', 'cost'])->groupBy(
            fn (ConversationAnalysis $a) => $a->analyzed_at->format('Y-m-d'),
        );

        return $grouped->map(function (Collection $items, string $day) {
            return [
                'period' => $day,
                'label' => JalaliDate::monthDay($day),
                'analyses' => $items->count(),
                'tokens' => (int) $items->sum('total_tokens'),
                'cost' => round((float) $items->sum('cost'), 4),
            ];
        })->values()->all();
    }

    public function drilldownAnalyses(ReportFilter $filter, int $limit = 20)
    {
        $query = $filter->applyToAnalysisQuery(
            ConversationAnalysis::query()->with(['employee.user:id,avatar_path,name']),
        )->latest('analyzed_at');

        if ($filter->drilldownDimension === 'employee' && $filter->drilldownValue) {
            $query->where('organization_user_id', (int) $filter->drilldownValue);
        }

        if ($filter->drilldownDimension === 'lead_level' && $filter->drilldownValue) {
            $level = (string) $filter->drilldownValue;
            $query->whereRaw("json_extract(lead_quality_json, '$.level') = ?", [$level]);
        }

        if ($filter->drilldownDimension === 'concern_type' && $filter->drilldownValue) {
            $type = (string) $filter->drilldownValue;
            $query->where('concerns_json', 'like', '%"type":"'.$type.'"%');
        }

        if ($filter->drilldownDimension === 'period' && $filter->drilldownValue) {
            $day = Carbon::parse((string) $filter->drilldownValue);
            $query->whereDate('analyzed_at', $day);
        }

        return $query->limit($limit)->get();
    }

    /** @return list<array<string, mixed>> */
    public function exportRows(ReportFilter $filter): array
    {
        return $filter->applyToAnalysisQuery(
            ConversationAnalysis::query()->with(['employee.user:id,avatar_path,name']),
        )
            ->latest('analyzed_at')
            ->get()
            ->map(fn (ConversationAnalysis $analysis) => [
                'date' => JalaliDate::datetime($analysis->analyzed_at),
                'employee' => $analysis->employee?->full_name ?? '—',
                'score' => $analysis->score,
                'lead_level' => $analysis->lead_quality_json['level'] ?? '—',
                'lead_score' => $analysis->lead_quality_json['score'] ?? '—',
                'concerns_count' => count($analysis->concerns_json ?? []),
                'sentiment' => $analysis->sentiment?->label() ?? '—',
                'cost' => $analysis->cost,
                'tokens' => $analysis->total_tokens,
            ])
            ->all();
    }

    private function delta(float|int $current, float|int $previous): ?float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
