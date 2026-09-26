<?php

namespace App\Services;

use App\Domain\Call\Enums\CallProcessingStatus;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Domain\Processing\Enums\ProcessingJobStatus;
use App\Domain\Voip\Enums\CallStatus;
use App\DTOs\AnalysisListFilter;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\OrganizationUser;
use App\Services\Reports\CallMetricsAnalytics;
use App\Services\Reports\DefinedExtensionCallConstraint;
use App\Support\CompanyWorkCalendar;
use App\Support\CustomerPresenter;
use App\Support\JalaliDate;
use App\Support\OrganizationHolidays;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalysisListQuery
{
    public function __construct(
        private CallMetricsAnalytics $callMetrics,
        private DefinedExtensionCallConstraint $definedExtensions,
    ) {}

    /** @return Builder<ConversationAnalysis> */
    public function baseQuery(AnalysisListFilter $filter): Builder
    {
        return $this->filteredQuery($filter)
            ->select('conversation_analyses.*')
            ->tap(fn (Builder $query) => $filter->applySort($query));
    }

    /** @return Builder<ConversationAnalysis> */
    private function filteredQuery(AnalysisListFilter $filter): Builder
    {
        $query = ConversationAnalysis::query()
            ->leftJoin('calls', 'conversation_analyses.call_id', '=', 'calls.id')
            ->leftJoin('voip_call_logs', 'conversation_analyses.voip_call_log_id', '=', 'voip_call_logs.id')
            ->leftJoin('organization_user', 'conversation_analyses.organization_user_id', '=', 'organization_user.id');

        return $filter->apply($query);
    }

    /** @return Builder<ConversationAnalysis> */
    private function analyticsQuery(AnalysisListFilter $filter): Builder
    {
        $moment = 'COALESCE(calls.conversation_date, calls.started_at, calls.created_at, conversation_analyses.analyzed_at)';

        return CompanyWorkCalendar::whereWorkday(
            $this->filteredQuery($filter),
            $moment,
            $this->holidayWeekdays($filter),
        );
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
        $query = $this->analyticsQuery($filter);

        // Score and the other analysis cards still follow analyzed_at.
        $avgScore = round((float) (clone $query)->evaluable()->avg('conversation_analyses.score'), 1);

        // Call-dated metrics (occurredAt via applyToCallQuery) — volume and outcomes
        // must follow when the call happened, not when AI finished analyzing.
        // Once the organization has registered extensions, only calls placed on
        // those extensions count (an unknown extension such as 112 is ignored).
        $callQuery = $this->definedExtensions->apply(
            $filter->applyToCallQuery(Call::query()),
            $filter->organizationId,
        );
        $totalCalls = (clone $callQuery)->count();
        // Missed calls plus finished calls equal the total. The only remainder
        // is a call still queued or being processed.
        $missedCount = (clone $callQuery)
            ->whereIn('status', CallStatus::lostValues())
            ->count();
        $inFlightCount = $this->inFlightCount(clone $callQuery);
        $analyzedCalls = $totalCalls - $missedCount - $inFlightCount;
        $avgDuration = (int) round((float) (clone $callQuery)
            ->where('duration_seconds', '>', 0)
            ->avg('duration_seconds'));

        $inboundCount = (clone $callQuery)->where('direction', 'inbound')->count();
        $outboundCount = (clone $callQuery)->where('direction', 'outbound')->count();

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
            'total' => $analyzedCalls,
            'total_calls' => $totalCalls,
            'average_score' => $avgScore,
            'average_duration_seconds' => $avgDuration,
            'average_duration_label' => $this->callMetrics->formatDuration($avgDuration),
            'missed_count' => $missedCount,
            'in_flight_count' => $inFlightCount,
            'inbound_count' => $inboundCount,
            'outbound_count' => $outboundCount,
            'average_lead_score' => $lead['average_score'] ?: null,
            'total_leads' => $lead['total'],
            'high_lead_count' => $lead['high'],
            'average_sentiment' => $averageSentiment,
            'dominant_sentiment' => collect($sentiment)->sortByDesc('count')->first()['label'] ?? null,
            'top_concern' => $topConcern['label'] ?? null,
            'top_agent_name' => $topAgent?->full_name,
            'top_agent_count' => (int) ($topAgentStats->agent_total ?? 0),
        ];
    }

    /** @param  Builder<Call>  $callQuery */
    private function inFlightCount(Builder $callQuery): int
    {
        return $callQuery
            ->where(function (Builder $query): void {
                $query->whereNull('status')
                    ->orWhereNotIn('status', CallStatus::lostValues());
            })
            ->where(function (Builder $query): void {
                $query->whereIn('processing_status', [
                    CallProcessingStatus::Pending->value,
                    CallProcessingStatus::Downloading->value,
                    CallProcessingStatus::Analyzing->value,
                ])->orWhere(function (Builder $unmarked) {
                    $unmarked->whereNull('processing_status')
                        ->whereHas('processingJobs', function (Builder $jobs): void {
                            $jobs->whereIn('status', [
                                ProcessingJobStatus::Queued->value,
                                ProcessingJobStatus::Uploading->value,
                                ProcessingJobStatus::Processing->value,
                            ]);
                        });
                });
            })
            ->count();
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
        $holidayWeekdays = $this->holidayWeekdays($filter);

        $grouped = $this->chartRows($filter)
            ->groupBy(fn (ConversationAnalysis $analysis) => $this->periodKey($this->chartOccurredAt($analysis), $granularity));

        return $grouped->map(function (Collection $items, string $period) use ($granularity) {
            $scored = $items->filter(fn (ConversationAnalysis $analysis) => $analysis->isEvaluable());

            return [
                'period' => $period,
                'label' => $this->periodLabel($period, $granularity),
                'avg_score' => $scored->isNotEmpty() ? round((float) $scored->avg('score'), 1) : null,
                'count' => $items->count(),
            ];
        })
            ->reject(fn (array $row) => $granularity === 'day' && CompanyWorkCalendar::isHoliday((string) $row['period'], $holidayWeekdays))
            ->values()
            ->all();
    }

    /** @return list<array{period: string, label: string, count: int}> */
    private function volumeTrend(AnalysisListFilter $filter): array
    {
        $granularity = $this->granularity($filter);
        $holidayWeekdays = $this->holidayWeekdays($filter);

        $grouped = $this->chartRows($filter)
            ->groupBy(fn (ConversationAnalysis $analysis) => $this->periodKey($this->chartOccurredAt($analysis), $granularity));

        return $grouped->map(function (Collection $items, string $period) use ($granularity) {
            return [
                'period' => $period,
                'label' => $this->periodLabel($period, $granularity),
                'count' => $items->count(),
            ];
        })->reject(fn (array $row) => $granularity === 'day' && CompanyWorkCalendar::isHoliday((string) $row['period'], $holidayWeekdays))
            ->values()
            ->all();
    }

    /** @return array{high: int, medium: int, low: int, total: int, average_score: float} */
    private function leadDistribution(AnalysisListFilter $filter): array
    {
        $distribution = ['high' => 0, 'medium' => 0, 'low' => 0];
        $scores = [];

        $this->analyticsQuery($filter)
            ->select(['conversation_analyses.id', 'conversation_analyses.lead_quality_json', 'conversation_analyses.is_evaluable', 'conversation_analyses.score'])
            ->chunkById(200, function (Collection $chunk) use (&$distribution, &$scores): void {
                foreach ($chunk as $analysis) {
                    if (! $analysis->isEvaluable()) {
                        continue;
                    }

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

        $this->analyticsQuery($filter)
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

        $this->analyticsQuery($filter)
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

    /** @return list<int> */
    private function holidayWeekdays(AnalysisListFilter $filter): array
    {
        return OrganizationHolidays::weekdays($filter->organizationId);
    }

    private function granularity(AnalysisListFilter $filter): string
    {
        $days = $filter->from->diffInDays($filter->to) + 1;

        return $days > 60 ? 'week' : 'day';
    }

    /** @return Collection<int, ConversationAnalysis> */
    private function chartRows(AnalysisListFilter $filter): Collection
    {
        return $this->analyticsQuery($filter)
            ->whereNotNull('conversation_analyses.analyzed_at')
            ->orderBy('conversation_analyses.analyzed_at')
            ->get([
                'conversation_analyses.analyzed_at',
                'conversation_analyses.score',
                'conversation_analyses.is_evaluable',
                'calls.conversation_date as call_conversation_date',
                'calls.started_at as call_started_at',
                'calls.created_at as call_created_at',
            ]);
    }

    private function chartOccurredAt(ConversationAnalysis $analysis): Carbon
    {
        foreach (['call_conversation_date', 'call_started_at', 'call_created_at', 'analyzed_at'] as $attribute) {
            $value = $analysis->getAttribute($attribute);

            if ($value) {
                return Carbon::parse($value);
            }
        }

        return Carbon::parse($analysis->analyzed_at);
    }

    private function periodKey(Carbon $date, string $granularity): string
    {
        return match ($granularity) {
            'week' => $date->format('Y-W'),
            default => CompanyWorkCalendar::dayKey($date),
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
