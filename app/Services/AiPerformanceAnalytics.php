<?php

namespace App\Services;

use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\OrganizationUser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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
        $totalCalls = Call::query()->where('organization_id', $this->organizationId)->count();
        $avgScore = round((float) (clone $query)->avg('score'), 1);
        $totalCost = round((float) (clone $query)->sum('cost'), 4);
        $totalTokens = (int) (clone $query)->sum('total_tokens');

        $employeeAvg = round((float) OrganizationUser::query()
            ->where('organization_id', $this->organizationId)
            ->whereHas('conversationAnalyses')
            ->withAvg('conversationAnalyses', 'score')
            ->get()
            ->avg('conversation_analyses_avg_score'), 1);

        $thisMonth = (clone $query)
            ->whereMonth('analyzed_at', now()->month)
            ->whereYear('analyzed_at', now()->year)
            ->avg('score');

        $lastMonth = (clone $query)
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
            ->withAvg('conversationAnalyses', 'score')
            ->withMax('conversationAnalyses', 'score')
            ->withMin('conversationAnalyses', 'score');

        if ($filters['department'] ?? null) {
            $query->where('department', $filters['department']);
        }

        if ($filters['employee_id'] ?? null) {
            $query->whereKey($filters['employee_id']);
        }

        return $query->get()->map(fn (OrganizationUser $employee) => [
            'id' => $employee->id,
            'name' => $employee->full_name,
            'department' => $employee->department,
            'average_score' => round((float) $employee->conversation_analyses_avg_score, 1),
            'total_analyzed' => $employee->conversation_analyses_count,
            'best_score' => $employee->conversation_analyses_max_score,
            'worst_score' => $employee->conversation_analyses_min_score,
            'common_strengths' => $this->commonItems($employee->id, 'strengths_json'),
            'common_weaknesses' => $this->commonItems($employee->id, 'weaknesses_json'),
        ]);
    }

    public function employeePerformanceInRange(\App\DTOs\ReportFilter $filter): Collection
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
                ->whereBetween('analyzed_at', [$from, $to]),
            ], 'score')
            ->get()
            ->filter(fn (OrganizationUser $employee) => $employee->total_analyzed > 0)
            ->map(fn (OrganizationUser $employee) => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'department' => $employee->department,
                'avatar_url' => $employee->avatarUrl(),
                'average_score' => round((float) $employee->average_score, 1),
                'total_analyzed' => (int) $employee->total_analyzed,
            ]);
    }

    public function organizationInsights(): array
    {
        $employees = $this->employeePerformance();

        return [
            'team_average' => round((float) $this->baseQuery()->avg('score'), 1),
            'top_performers' => $employees->sortByDesc('average_score')->take(3)->values()->all(),
            'lowest_performers' => $employees->sortBy('average_score')->take(3)->values()->all(),
            'coaching_opportunities' => $this->coachingOpportunities(),
        ];
    }

    public function scoreTrend(string $period = 'day', ?Carbon $from = null, ?Carbon $to = null, ?int $employeeId = null): array
    {
        $from ??= now()->subDays(30);
        $to ??= now();

        $query = $this->baseQuery()
            ->whereBetween('analyzed_at', [$from, $to])
            ->orderBy('analyzed_at');

        if ($employeeId) {
            $query->where('organization_user_id', $employeeId);
        }

        $grouped = $query->get()->groupBy(function (ConversationAnalysis $analysis) use ($period) {
            return match ($period) {
                'week' => $analysis->analyzed_at->format('Y-W'),
                'month' => $analysis->analyzed_at->format('Y-m'),
                default => $analysis->analyzed_at->format('Y-m-d'),
            };
        });

        return $grouped->map(fn (Collection $items, string $key) => [
            'period' => $key,
            'avg_score' => round((float) $items->avg('score'), 1),
            'count' => $items->count(),
        ])->values()->all();
    }

    private function averageSentimentScore(): float
    {
        $weights = [
            AnalysisSentiment::Positive->value => 100,
            AnalysisSentiment::Mixed->value => 60,
            AnalysisSentiment::Neutral->value => 50,
            AnalysisSentiment::Negative->value => 20,
        ];

        $analyses = $this->baseQuery()->get(['sentiment']);

        if ($analyses->isEmpty()) {
            return 0;
        }

        $total = $analyses->sum(fn (ConversationAnalysis $analysis) => $weights[$analysis->sentiment->value] ?? 50);

        return round($total / $analyses->count(), 1);
    }

    private function commonItems(int $employeeId, string $column): array
    {
        $analyses = $this->baseQuery()
            ->where('organization_user_id', $employeeId)
            ->latest('analyzed_at')
            ->limit(20)
            ->get();

        $counts = [];

        foreach ($analyses as $analysis) {
            foreach ($analysis->{$column} ?? [] as $item) {
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
