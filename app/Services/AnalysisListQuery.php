<?php

namespace App\Services;

use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Domain\Voip\Enums\CallStatus;
use App\DTOs\AnalysisListFilter;
use App\Models\ConversationAnalysis;
use App\Models\OrganizationUser;
use App\Services\Reports\CallMetricsAnalytics;
use App\Support\CustomerPresenter;
use App\Support\JalaliDate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalysisListQuery
{
    public function __construct(private CallMetricsAnalytics $callMetrics) {}

    /** @return Builder<ConversationAnalysis> */
    public function baseQuery(AnalysisListFilter $filter): Builder
    {
        return $this->filteredQuery($filter)->tap(
            fn (Builder $query) => $filter->applySort($query),
        );
    }

    /** @return Builder<ConversationAnalysis> */
    private function filteredQuery(AnalysisListFilter $filter): Builder
    {
        $query = ConversationAnalysis::query()
            ->leftJoin('calls', 'conversation_analyses.call_id', '=', 'calls.id')
            ->leftJoin('voip_call_logs', 'conversation_analyses.voip_call_log_id', '=', 'voip_call_logs.id')
            ->leftJoin('organization_user', 'conversation_analyses.organization_user_id', '=', 'organization_user.id')
            ->select('conversation_analyses.*');

        return $filter->apply($query);
    }

    public function paginate(AnalysisListFilter $filter, int $perPage = 20): LengthAwarePaginator
    {
        return $this->baseQuery($filter)
            ->with(['employee.user', 'call', 'callLog'])
            ->paginate($perPage);
    }

    /** @return array<string, mixed> */
    public function overview(AnalysisListFilter $filter): array
    {
        $query = $this->filteredQuery($filter);

        $total = (clone $query)->count();
        $avgScore = round((float) (clone $query)->avg('conversation_analyses.score'), 1);

        $avgDuration = (int) round((float) (clone $query)
            ->avg(DB::raw('COALESCE(calls.duration_seconds, voip_call_logs.duration, 0)')));

        $missedCount = (clone $query)->where(function (Builder $inner) {
            $inner->where('calls.status', CallStatus::Missed->value)
                ->orWhere('voip_call_logs.status', CallStatus::Missed->value);
        })->count();

        $inboundCount = (clone $query)->where(function (Builder $inner) {
            $inner->where('calls.direction', 'inbound')
                ->orWhere('voip_call_logs.direction', 'inbound');
        })->count();

        $outboundCount = (clone $query)->where(function (Builder $inner) {
            $inner->where('calls.direction', 'outbound')
                ->orWhere('voip_call_logs.direction', 'outbound');
        })->count();

        $lead = $this->leadDistribution($filter);
        $sentiment = $this->sentimentBreakdown($filter);
        $topConcern = $this->concernsByType($filter)[0] ?? null;

        $sentimentWeights = [
            AnalysisSentiment::Positive->value => 100,
            AnalysisSentiment::Mixed->value => 60,
            AnalysisSentiment::Neutral->value => 50,
            AnalysisSentiment::Negative->value => 20,
        ];

        $averageSentiment = null;
        if ($sentiment !== []) {
            $totalSentiment = array_sum(array_column($sentiment, 'count'));
            $weighted = collect($sentiment)->sum(
                fn (array $item) => ($sentimentWeights[$item['key']] ?? 50) * $item['count'],
            );
            $averageSentiment = $totalSentiment > 0 ? round($weighted / $totalSentiment, 1) : null;
        }

        $topAgentStats = (clone $query)
            ->whereNotNull('conversation_analyses.organization_user_id')
            ->select([
                'conversation_analyses.organization_user_id as agent_id',
                DB::raw('COUNT(*) as agent_total'),
            ])
            ->groupBy('conversation_analyses.organization_user_id')
            ->orderByDesc('agent_total')
            ->first();

        $topAgent = $topAgentStats
            ? OrganizationUser::query()->find($topAgentStats->agent_id)
            : null;

        return [
            'total' => $total,
            'average_score' => $avgScore,
            'average_duration_seconds' => $avgDuration,
            'average_duration_label' => $this->callMetrics->formatDuration($avgDuration),
            'missed_count' => $missedCount,
            'inbound_count' => $inboundCount,
            'outbound_count' => $outboundCount,
            'average_lead_score' => $lead['average_score'] ?: null,
            'high_lead_count' => $lead['high'],
            'average_sentiment' => $averageSentiment,
            'dominant_sentiment' => collect($sentiment)->sortByDesc('count')->first()['label'] ?? null,
            'top_concern' => $topConcern['label'] ?? null,
            'top_agent_name' => $topAgent?->full_name,
            'top_agent_count' => (int) ($topAgentStats->agent_total ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    public function charts(AnalysisListFilter $filter): array
    {
        return [
            'quality_trend' => $this->qualityTrend($filter),
            'volume_trend' => $this->volumeTrend($filter),
            'lead_distribution' => $this->leadDistribution($filter),
            'sentiment_breakdown' => $this->sentimentBreakdown($filter),
            'concerns' => $this->concernsByType($filter),
        ];
    }

    /** @return list<array{period: string, label: string, avg_score: float|null, count: int}> */
    private function qualityTrend(AnalysisListFilter $filter): array
    {
        $granularity = $this->granularity($filter);

        $grouped = $this->filteredQuery($filter)
            ->whereNotNull('conversation_analyses.analyzed_at')
            ->orderBy('conversation_analyses.analyzed_at')
            ->get(['conversation_analyses.analyzed_at', 'conversation_analyses.score'])
            ->groupBy(fn (ConversationAnalysis $analysis) => $this->periodKey($analysis->analyzed_at, $granularity));

        return $grouped->map(function (Collection $items, string $period) use ($granularity) {
            return [
                'period' => $period,
                'label' => $this->periodLabel($period, $granularity),
                'avg_score' => round((float) $items->avg('score'), 1),
                'count' => $items->count(),
            ];
        })->values()->all();
    }

    /** @return list<array{period: string, label: string, count: int}> */
    private function volumeTrend(AnalysisListFilter $filter): array
    {
        $granularity = $this->granularity($filter);

        $grouped = $this->filteredQuery($filter)
            ->whereNotNull('conversation_analyses.analyzed_at')
            ->orderBy('conversation_analyses.analyzed_at')
            ->get(['conversation_analyses.analyzed_at'])
            ->groupBy(fn (ConversationAnalysis $analysis) => $this->periodKey($analysis->analyzed_at, $granularity));

        return $grouped->map(function (Collection $items, string $period) use ($granularity) {
            return [
                'period' => $period,
                'label' => $this->periodLabel($period, $granularity),
                'count' => $items->count(),
            ];
        })->values()->all();
    }

    /** @return array{high: int, medium: int, low: int, total: int, average_score: float} */
    private function leadDistribution(AnalysisListFilter $filter): array
    {
        $distribution = ['high' => 0, 'medium' => 0, 'low' => 0];
        $scores = [];

        $this->filteredQuery($filter)
            ->select(['conversation_analyses.id', 'conversation_analyses.lead_quality_json'])
            ->chunkById(200, function (Collection $chunk) use (&$distribution, &$scores): void {
                foreach ($chunk as $analysis) {
                    $lead = $analysis->lead_quality_json;
                    if (! is_array($lead) || $lead === []) {
                        continue;
                    }

                    $level = strtolower((string) ($lead['level'] ?? 'medium'));
                    if (! isset($distribution[$level])) {
                        $level = 'medium';
                    }
                    $distribution[$level]++;

                    if (isset($lead['score'])) {
                        $scores[] = (int) $lead['score'];
                    }
                }
            }, 'conversation_analyses.id', 'id');

        return [
            'high' => $distribution['high'],
            'medium' => $distribution['medium'],
            'low' => $distribution['low'],
            'total' => array_sum($distribution),
            'average_score' => $scores !== [] ? round(array_sum($scores) / count($scores), 1) : 0,
        ];
    }

    /** @return list<array{key: string, label: string, count: int}> */
    private function sentimentBreakdown(AnalysisListFilter $filter): array
    {
        $counts = [];

        $this->filteredQuery($filter)
            ->select(['conversation_analyses.id', 'conversation_analyses.sentiment'])
            ->chunkById(200, function (Collection $chunk) use (&$counts): void {
                foreach ($chunk as $analysis) {
                    if (! $analysis->sentiment) {
                        continue;
                    }

                    $key = $analysis->sentiment->value;
                    $counts[$key] = ($counts[$key] ?? 0) + 1;
                }
            }, 'conversation_analyses.id', 'id');

        return collect($counts)
            ->map(function (int $count, string $key) {
                $sentiment = AnalysisSentiment::tryFrom($key);

                return [
                    'key' => $key,
                    'label' => $sentiment?->label() ?? $key,
                    'count' => $count,
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /** @return list<array{type: string, label: string, count: int}> */
    private function concernsByType(AnalysisListFilter $filter): array
    {
        $counts = [];

        $this->filteredQuery($filter)
            ->select(['conversation_analyses.id', 'conversation_analyses.concerns_json'])
            ->chunkById(200, function (Collection $chunk) use (&$counts): void {
                foreach ($chunk as $analysis) {
                    foreach ($analysis->concerns_json ?? [] as $concern) {
                        if (! is_array($concern)) {
                            continue;
                        }

                        $type = strtolower((string) ($concern['type'] ?? 'other'));
                        $counts[$type] = ($counts[$type] ?? 0) + 1;
                    }
                }
            }, 'conversation_analyses.id', 'id');

        return collect($counts)
            ->map(fn (int $count, string $type) => [
                'type' => $type,
                'label' => CustomerPresenter::concernLabel($type),
                'count' => $count,
            ])
            ->sortByDesc('count')
            ->values()
            ->take(5)
            ->all();
    }

    private function granularity(AnalysisListFilter $filter): string
    {
        $days = $filter->from->diffInDays($filter->to) + 1;

        return $days > 60 ? 'week' : 'day';
    }

    private function periodKey(\Carbon\Carbon $date, string $granularity): string
    {
        return match ($granularity) {
            'week' => $date->format('Y-W'),
            default => $date->format('Y-m-d'),
        };
    }

    private function periodLabel(string $key, string $granularity): string
    {
        if ($granularity === 'week') {
            return 'هفته '.$key;
        }

        return JalaliDate::monthDay($key);
    }
}
