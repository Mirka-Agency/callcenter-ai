<?php

namespace App\Services;

use App\DTOs\ReportFilter;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Services\Reports\DefinedExtensionCallConstraint;
use App\Models\OrganizationUser;
use App\Services\Performance\Calculators\SentimentScoreCalculator;
use App\Services\Reports\ChartHolidayCalendar;
use App\Support\CompanyWorkCalendar;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AiPerformanceAnalytics
{
    public function __construct(private int $organizationId) {}

    public static function forOrganization(int $organizationId): self
    {
        return new self($organizationId);
    }

    public function baseQuery(): Builder
    {
        return ConversationAnalysis::query()
            ->where('organization_id', $this->organizationId);
    }

    public function overview(): array
    {
        $query = $this->baseQuery();

        $totalAnalyzed = (clone $query)->count();
        $totalCalls = app(DefinedExtensionCallConstraint::class)->apply(
            Call::query()->where('organization_id', $this->organizationId),
            $this->organizationId,
        )->count();
        $avgScore = round((float) (clone $query)->evaluable()->avg('score'), 1);
        $totalCost = round((float) (clone $query)->sum('cost'), 4);
        $totalTokens = (int) (clone $query)->sum('total_tokens');

        $employeeAvg = round((float) OrganizationUser::query()
            ->where('organization_id', $this->organizationId)
            ->whereHas('conversationAnalyses')
            ->withAvg(['conversationAnalyses' => fn (Builder $q) => $q->evaluable()], 'score')
            ->get()
            ->avg('conversation_analyses_avg_score'), 1);

        $thisMonth = (clone $query)
            ->evaluable()
            ->whereMonth('analyzed_at', now()->month)
            ->whereYear('analyzed_at', now()->year)
            ->avg('score');

        $lastMonth = (clone $query)
            ->evaluable()
            ->whereMonth('analyzed_at', now()->subMonth()->month)
            ->whereYear('analyzed_at', now()->subMonth()->year)
            ->avg('score');

        $improvement = $thisMonth && $lastMonth
            ? round($thisMonth - $lastMonth, 1)
            : 0;

        $avgSentiment = $this->averageSentimentScore();

        return [
            'total_calls' => $totalCalls,
            'total_analyzed' => $totalAnalyzed,
            'average_score' => $avgScore,
            'average_sentiment' => $avgSentiment,
            'employee_average_score' => $employeeAvg ?: 0,
            'monthly_improvement' => $improvement,
            'total_cost' => $totalCost,
            'total_tokens' => $totalTokens,
        ];
    }

    public function employeePerformance(?array $filters = null): Collection
    {
        $query = OrganizationUser::query()
            ->where('organization_id', $this->organizationId)
            ->withCount('conversationAnalyses')
            ->withAvg(['conversationAnalyses' => fn (Builder $q) => $q->evaluable()], 'score')
            ->withMax(['conversationAnalyses' => fn (Builder $q) => $q->evaluable()], 'score')
            ->withMin(['conversationAnalyses' => fn (Builder $q) => $q->evaluable()], 'score');

        if ($filters['department'] ?? null) {
            $query->where('department', $filters['department']);
        }

        if ($filters['employee_id'] ?? null) {
            $query->whereKey($filters['employee_id']);
        }

        $employees = $query->get();
        $recent = $this->recentItemsByEmployee($employees->pluck('id'));

        return $employees->map(fn (OrganizationUser $employee) => [
            'id' => $employee->id,
            'name' => $employee->full_name,
            'department' => $employee->department,
            'average_score' => round((float) $employee->conversation_analyses_avg_score, 1),
            'total_analyzed' => $employee->conversation_analyses_count,
            'best_score' => $employee->conversation_analyses_max_score,
            'worst_score' => $employee->conversation_analyses_min_score,
            'common_strengths' => $this->commonItemsFromRows($recent->get($employee->id, collect()), 'strengths_json'),
            'common_weaknesses' => $this->commonItemsFromRows($recent->get($employee->id, collect()), 'weaknesses_json'),
        ]);
    }

    public function employeePerformanceInRange(ReportFilter $filter): Collection
    {
        $query = OrganizationUser::query()
            ->where('organization_id', $this->organizationId)
            ->where('is_active', true);

        if ($filter->employeeIds !== []) {
            $query->whereIn('id', $filter->employeeIds);
        }

        $from = $filter->from;
        $to = $filter->to;

        return $query
            ->with('user:id,avatar_path,name')
            ->withCount(['conversationAnalyses as total_analyzed' => fn (Builder $q) => $q
                ->whereBetween('analyzed_at', [$from, $to]),
            ])
            ->withAvg(['conversationAnalyses as average_score' => fn (Builder $q) => $q
                ->evaluable()
                ->whereBetween('analyzed_at', [$from, $to]),
            ], 'score')
            ->get()
            ->filter(fn (OrganizationUser $employee) => $employee->total_analyzed > 0)
            ->map(fn (OrganizationUser $employee) => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'department' => $employee->department,
                'avatar_url' => $employee->avatarUrl(),
                'gender' => $employee->gender?->value,
                'average_score' => round((float) $employee->average_score, 1),
                'total_analyzed' => (int) $employee->total_analyzed,
            ]);
    }

    public function organizationInsights(): array
    {
        $employees = $this->employeePerformance();

        return [
            'team_average' => round((float) $this->baseQuery()->evaluable()->avg('score'), 1),
            'top_performers' => $employees->sortByDesc('average_score')->take(3)->values()->all(),
            'lowest_performers' => $employees->sortBy('average_score')->take(3)->values()->all(),
            'coaching_opportunities' => $this->coachingOpportunities(),
        ];
    }

    public function scoreTrend(string $period = 'day', ?Carbon $from = null, ?Carbon $to = null, ?int $employeeId = null): array
    {
        $from ??= now()->subDays(30);
        $to ??= now();

        if ($period === 'week') {
            return $this->weeklyScoreTrend($from, $to, $employeeId);
        }

        return $this->bucketedScoreTrend($period, $from, $to, $employeeId);
    }

    /** @return list<array{period: string, avg_score: float, count: int}> */
    private function bucketedScoreTrend(string $period, Carbon $from, Carbon $to, ?int $employeeId): array
    {
        $driver = DB::connection()->getDriverName();
        $query = ConversationAnalysis::query()
            ->business()
            ->where('conversation_analyses.organization_id', $this->organizationId)
            ->whereBetween('conversation_analyses.analyzed_at', [$from, $to]);

        if ($employeeId) {
            $query->where('conversation_analyses.organization_user_id', $employeeId);
        }

        if ($period === 'day') {
            $query->leftJoin('calls', 'calls.id', '=', 'conversation_analyses.call_id');
            $bucket = CompanyWorkCalendar::sqlDayKey(
                'COALESCE(calls.conversation_date, calls.started_at, calls.created_at, conversation_analyses.analyzed_at)',
                $driver,
            );
        } else {
            $bucket = match ($driver) {
                'pgsql' => "to_char(conversation_analyses.analyzed_at, 'YYYY-MM')",
                'mysql', 'mariadb' => "DATE_FORMAT(conversation_analyses.analyzed_at, '%Y-%m')",
                default => "strftime('%Y-%m', conversation_analyses.analyzed_at)",
            };
        }

        $evaluable = $driver === 'pgsql'
            ? '(conversation_analyses.is_evaluable IS NULL OR conversation_analyses.is_evaluable IS TRUE)'
            : '(conversation_analyses.is_evaluable IS NULL OR conversation_analyses.is_evaluable = 1)';
        $base = $query->toBase();
        $base->columns = [];
        $closedDays = $period === 'day'
            ? app(ChartHolidayCalendar::class)->forRange($this->organizationId, $from, $to)
            : null;
        $series = [];

        foreach ($base
            ->selectRaw($bucket.' as period_key')
            ->selectRaw('AVG(CASE WHEN '.$evaluable.' AND conversation_analyses.score > 0 THEN conversation_analyses.score END) as avg_score')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupByRaw($bucket)
            ->orderByRaw($bucket)
            ->get() as $row) {
            $key = $period === 'day'
                ? substr((string) $row->period_key, 0, 10)
                : substr((string) $row->period_key, 0, 7);

            if ($key === '' || ($closedDays !== null && $closedDays->hides($key))) {
                continue;
            }

            $series[] = [
                'period' => $key,
                'avg_score' => round((float) ($row->avg_score ?? 0), 1),
                'count' => (int) $row->aggregate,
            ];
        }

        return $series;
    }

    /** @return list<array{period: string, avg_score: float, count: int}> */
    private function weeklyScoreTrend(Carbon $from, Carbon $to, ?int $employeeId): array
    {
        $query = $this->baseQuery()
            ->business()
            ->whereBetween('analyzed_at', [$from, $to])
            ->orderBy('analyzed_at');

        if ($employeeId) {
            $query->where('organization_user_id', $employeeId);
        }

        $grouped = $query->get(['id', 'score', 'is_evaluable', 'analyzed_at'])
            ->groupBy(fn (ConversationAnalysis $analysis) => $analysis->analyzed_at->format('Y-W'));

        return $grouped->map(fn (Collection $items, string $key) => [
            'period' => $key,
            'avg_score' => round((float) $items->filter(fn (ConversationAnalysis $analysis) => $analysis->isEvaluable())->avg('score'), 1),
            'count' => $items->count(),
        ])->values()->all();
    }

    private function averageSentimentScore(): float
    {
        $weight = SentimentScoreCalculator::weightExpression('sentiment');
        $stats = $this->baseQuery()->business()->toBase();
        $stats->columns = [];
        $row = $stats
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(COALESCE('.$weight.', 50)) as weighted')
            ->first();
        $total = (int) ($row->total ?? 0);

        if ($total === 0) {
            return 0;
        }

        return round(((float) $row->weighted) / $total, 1);
    }

    /**
     * Latest 20 analyses per employee, loaded once for the whole team.
     *
     * @param  Collection<int, int>  $employeeIds
     * @return Collection<int|string, Collection<int, object>>
     */
    private function recentItemsByEmployee(Collection $employeeIds): Collection
    {
        if ($employeeIds->isEmpty()) {
            return collect();
        }

        return DB::query()
            ->fromSub(function ($query) use ($employeeIds): void {
                $query->from('conversation_analyses')
                    ->select(['organization_user_id', 'strengths_json', 'weaknesses_json'])
                    ->selectRaw('ROW_NUMBER() OVER (PARTITION BY organization_user_id ORDER BY analyzed_at DESC, id DESC) as item_rank')
                    ->where('organization_id', $this->organizationId)
                    ->whereIn('organization_user_id', $employeeIds->all());
            }, 'ranked_analyses')
            ->where('item_rank', '<=', 20)
            ->get()
            ->groupBy('organization_user_id');
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return list<mixed>
     */
    private function commonItemsFromRows(Collection $rows, string $column): array
    {
        $counts = [];

        foreach ($rows as $row) {
            $items = $row->{$column} ?? [];

            if (is_string($items)) {
                $items = json_decode($items, true) ?: [];
            }

            if (! is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                if (! is_string($item) && ! is_int($item)) {
                    continue;
                }

                $counts[$item] = ($counts[$item] ?? 0) + 1;
            }
        }

        arsort($counts);

        return array_slice(array_keys($counts), 0, 5);
    }

    private function coachingOpportunities(): array
    {
        $weaknesses = [];

        $this->baseQuery()
            ->latest('analyzed_at')
            ->limit(50)
            ->get()
            ->each(function (ConversationAnalysis $analysis) use (&$weaknesses) {
                foreach ($analysis->weaknesses_json ?? [] as $weakness) {
                    $weaknesses[$weakness] = ($weaknesses[$weakness] ?? 0) + 1;
                }
            });

        arsort($weaknesses);

        return array_slice(array_map(
            fn ($item, $count) => ['weakness' => $item, 'count' => $count],
            array_keys($weaknesses),
            array_values($weaknesses),
        ), 0, 5);
    }
}
