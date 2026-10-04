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
use App\Services\CallIntake\CallIntakePolicy;
use App\Services\CallIntake\CallIntakeSettings;
use App\Services\Performance\Calculators\SentimentScoreCalculator;
use App\Services\Reports\CallMetricsAnalytics;
use App\Services\Reports\ChartHolidayCalendar;
use App\Services\Reports\DefinedExtensionCallConstraint;
use App\Services\Reports\OrganizationCallMetrics;
use App\Services\Reports\ProcessingQueueCallStats;
use App\Support\AnalysisInsightPresenter;
use App\Support\ChartDayFilter;
use App\Support\CompanyWorkCalendar;
use App\Support\CustomerPresenter;
use App\Support\JalaliDate;
use App\Support\OrganizationHolidays;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalysisListQuery
{
    /** @var array<int, Collection<int, ConversationAnalysis>> */
    private array $factsByFilter = [];

    /** @var array<int, Collection<int, object>> */
    private array $dailyBucketsByFilter = [];

    /** @var array<int, array{lead: array{high: int, medium: int, low: int, total: int, average_score: float}, sentiment: list<array{key: string, label: string, count: int}>, concerns: list<array{type: string, label: string, count: int}>}> */
    private array $factRollups = [];

    public function __construct(
        private CallMetricsAnalytics $callMetrics,
        private DefinedExtensionCallConstraint $definedExtensions,
        private ProcessingQueueCallStats $queueCallStats,
        private OrganizationCallMetrics $organizationCallMetrics,
    ) {}

    /** @return Builder<ConversationAnalysis> */
    public function baseQuery(AnalysisListFilter $filter): Builder
    {
        return $this->filteredQuery($filter)
            ->select([
                'conversation_analyses.id',
                'conversation_analyses.organization_id',
                'conversation_analyses.organization_user_id',
                'conversation_analyses.call_id',
                'conversation_analyses.voip_call_log_id',
                'conversation_analyses.score',
                'conversation_analyses.is_evaluable',
                'conversation_analyses.needs_attention',
                'conversation_analyses.summary',
                'conversation_analyses.source',
                'conversation_analyses.analyzed_at',
                'conversation_analyses.sentiment',
            ])
            ->tap(fn (Builder $query) => $filter->applySort($query));
    }

    /** @return Builder<ConversationAnalysis> */
    private function filteredQuery(AnalysisListFilter $filter): Builder
    {
        $query = ConversationAnalysis::query()
            ->business()
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
        $facts = $this->analysisFacts($filter);
        $evaluableFacts = $facts->filter(fn (ConversationAnalysis $analysis): bool => (bool) $analysis->is_evaluable && (int) $analysis->score > 0);
        $avgScore = $evaluableFacts->isNotEmpty()
            ? round((float) $evaluableFacts->avg('score'), 1)
            : 0.0;

        // Call-dated metrics (occurredAt via applyToCallQuery) — volume and outcomes
        // must follow when the call happened, not when AI finished analyzing.
        // Once the organization has registered extensions, only calls placed on
        // those extensions count (an unknown extension such as 112 is ignored).
        $callStats = $this->callStats($filter);
        $totalCalls = $callStats['total_calls'];
        $outsideAnalysisCount = $callStats['outside_analysis_count'];
        $missedCount = $callStats['missed_count'];
        $inFlightCount = $callStats['in_flight_count'];
        // Completion window — same definition as the employer dashboard "تماس‌های تحلیل‌شده" card.
        $analyzedCalls = $this->analyzedCount($filter);
        $avgDuration = $callStats['average_duration_seconds'];
        $inboundCount = $callStats['inbound_count'];
        $outboundCount = $callStats['outbound_count'];

        $lead = $this->leadDistribution($filter);
        $sentiment = $this->sentimentBreakdown($filter);
        $topConcern = $this->concernsByType($filter)[0] ?? null;

        $sentimentWeights = SentimentScoreCalculator::weights();

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
            'outside_analysis_count' => $outsideAnalysisCount,
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

    /**
     * One pass over the call window. Repeating this scan for each card was the slow part of the page.
     *
     * @return array{
     *     total_calls: int,
     *     outside_analysis_count: int,
     *     missed_count: int,
     *     in_flight_count: int,
     *     inbound_count: int,
     *     outbound_count: int,
     *     average_duration_seconds: int
     * }
     */
    private function callStats(AnalysisListFilter $filter): array
    {
        $extensionKey = md5(json_encode($this->definedExtensions->matchSetFingerprint($filter->organizationId)) ?: '');
        $cacheKey = implode(':', [
            'analysis-call-stats-recorded-v7',
            app(CallIntakeSettings::class)->cacheToken($filter->organizationId),
            $filter->organizationId,
            $filter->from->getTimestamp(),
            $filter->to->getTimestamp(),
            $filter->employeeId ?? 'all',
            $filter->direction ?? '',
            implode(',', $filter->statuses),
            $filter->minDurationSeconds ?? '',
            $filter->maxDurationSeconds ?? '',
            $extensionKey,
        ]);

        return Cache::remember($cacheKey, 90, function () use ($filter): array {
            $callQuery = $this->definedExtensions->apply(
                $filter->applyToCallQuery(Call::query()),
                $filter->organizationId,
            );
            $lost = CallStatus::lostValues();
            $marked = [
                CallProcessingStatus::Pending->value,
                CallProcessingStatus::Downloading->value,
                CallProcessingStatus::Analyzing->value,
            ];
            $activeJobs = [
                ProcessingJobStatus::Queued->value,
                ProcessingJobStatus::Uploading->value,
                ProcessingJobStatus::Processing->value,
            ];
            $lostSql = implode(', ', array_fill(0, count($lost), '?'));
            $markedSql = implode(', ', array_fill(0, count($marked), '?'));
            $jobsSql = implode(', ', array_fill(0, count($activeJobs), '?'));
            $row = (clone $callQuery)
                ->selectRaw('COUNT(*) as total_calls')
                ->selectRaw(
                    "SUM(CASE WHEN status IN ($lostSql) THEN 1 ELSE 0 END) as missed_count",
                    $lost,
                )
                ->selectRaw("SUM(CASE WHEN direction = 'inbound' THEN 1 ELSE 0 END) as inbound_count")
                ->selectRaw("SUM(CASE WHEN direction = 'outbound' THEN 1 ELSE 0 END) as outbound_count")
                ->selectRaw('AVG(CASE WHEN duration_seconds > 0 THEN duration_seconds END) as avg_duration')
                ->selectRaw(
                    "SUM(CASE WHEN (status IS NULL OR status NOT IN ($lostSql)) AND (processing_status IN ($markedSql) OR (processing_status IS NULL AND EXISTS (SELECT 1 FROM call_processing_jobs WHERE call_processing_jobs.call_id = calls.id AND call_processing_jobs.status IN ($jobsSql)))) THEN 1 ELSE 0 END) as in_flight_count",
                    [...$lost, ...$marked, ...$activeJobs],
                )
                ->first();

            $outsideAnalysisCount = $this->definedExtensions->applyOutsideAnalysis(
                $filter->applyToCallQuery(Call::query()),
                $filter->organizationId,
            )->count();

            // Match dashboard "تماس‌های امروز" / countBetween when the page is only date-scoped:
            // include orphan VoIP logs on defined extensions (no Call row yet).
            $totalCalls = $filter->hasCallAttributeFilters()
                ? (int) ($row->total_calls ?? 0)
                : $this->organizationCallMetrics->countBetween(
                    $filter->organizationId,
                    $filter->from,
                    $filter->to,
                );

            return [
                'total_calls' => $totalCalls,
                'outside_analysis_count' => $outsideAnalysisCount,
                'missed_count' => (int) ($row->missed_count ?? 0),
                'in_flight_count' => (int) ($row->in_flight_count ?? 0),
                'inbound_count' => (int) ($row->inbound_count ?? 0),
                'outbound_count' => (int) ($row->outbound_count ?? 0),
                'average_duration_seconds' => (int) round((float) ($row->avg_duration ?? 0)),
            ];
        });
    }

    /**
     * Analyses completed in the filter window — mirrors EmployeePerformanceAnalytics::total_analyzed.
     * Call occurrence date, extension queue rules, and holidays must not shrink this card.
     */
    private function analyzedCount(AnalysisListFilter $filter): int
    {
        $query = $this->filteredQuery($filter);

        if ($filter->employeeId === null) {
            $activeIds = OrganizationUser::query()
                ->where('organization_id', $filter->organizationId)
                ->where('is_active', true)
                ->pluck('id');

            if ($activeIds->isEmpty()) {
                return 0;
            }

            $query->whereIn('conversation_analyses.organization_user_id', $activeIds->all());
        }

        return (int) app(CallIntakePolicy::class)
            ->applyToAnalyses($query, $filter->organizationId)
            ->toBase()
            ->selectRaw('count(distinct conversation_analyses.id) as aggregate')
            ->value('aggregate');
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

    /** @return list<array{period: string, label: string, tooltip_label: string, avg_score: float|null, count: int}> */
    private function qualityTrend(AnalysisListFilter $filter): array
    {
        $granularity = $this->granularity($filter);
        $days = $this->chartDays($filter);
        $buckets = $this->chartBuckets($filter, $granularity);

        return $buckets->map(function (object $bucket) use ($granularity): array {
            $scored = (int) $bucket->scored_count;
            $period = $this->bucketPeriod($bucket, $granularity);

            return [
                'period' => $period,
                'label' => $this->periodLabel($period, $granularity),
                'tooltip_label' => $this->periodTooltipLabel($period, $granularity),
                'avg_score' => $scored > 0 ? round(((float) $bucket->score_sum) / $scored, 1) : null,
                'count' => (int) $bucket->total_count,
            ];
        })
            ->reject(fn (array $row) => $granularity === 'day' && $days->hides((string) $row['period']))
            ->values()
            ->all();
    }

    /** @return list<array{period: string, label: string, tooltip_label: string, count: int}> */
    private function volumeTrend(AnalysisListFilter $filter): array
    {
        $granularity = $this->granularity($filter);
        $days = $this->chartDays($filter);

        return $this->chartBuckets($filter, $granularity)
            ->map(function (object $bucket) use ($granularity): array {
                $period = $this->bucketPeriod($bucket, $granularity);

                return [
                    'period' => $period,
                    'label' => $this->periodLabel($period, $granularity),
                    'tooltip_label' => $this->periodTooltipLabel($period, $granularity),
                    'count' => (int) $bucket->total_count,
                ];
            })
            ->reject(fn (array $row) => $granularity === 'day' && $days->hides((string) $row['period']))
            ->values()
            ->all();
    }

    /** @return array{high: int, medium: int, low: int, total: int, average_score: float} */
    private function leadDistribution(AnalysisListFilter $filter): array
    {
        return $this->factRollup($filter)['lead'];
    }

    /** @return list<array{key: string, label: string, count: int}> */
    private function sentimentBreakdown(AnalysisListFilter $filter): array
    {
        return $this->factRollup($filter)['sentiment'];
    }

    /** @return list<array{type: string, label: string, count: int}> */
    private function concernsByType(AnalysisListFilter $filter): array
    {
        return $this->factRollup($filter)['concerns'];
    }

    /**
     * Calls in the current filter whose analysis includes the selected concern type.
     *
     * @return array{total: int, calls: list<array{
     *     analysis_id: int,
     *     customer: string,
     *     employee: string,
     *     date: string,
     *     duration_label: string,
     *     quality_score: int|null,
     *     summary: string|null,
     *     concerns: list<array{text: string, severity: string}>
     * }>}
     */
    public function callsForConcern(AnalysisListFilter $filter, ?string $type, int $limit = 20): array
    {
        $type = is_string($type) ? strtolower(trim($type)) : '';

        if ($type === '' || mb_strlen($type) > 32) {
            return ['total' => 0, 'calls' => []];
        }

        $allowed = collect($this->concernsByType($filter))->pluck('type')->all();

        if (! in_array($type, $allowed, true)) {
            return ['total' => 0, 'calls' => []];
        }

        $matchingIds = [];

        foreach ($this->analysisFacts($filter) as $analysis) {
            if ($this->analysisHasConcernType($analysis, $type)) {
                $matchingIds[] = (int) $analysis->id;
            }
        }

        if ($matchingIds === []) {
            return ['total' => 0, 'calls' => []];
        }

        $calls = ConversationAnalysis::query()
            ->where('organization_id', $filter->organizationId)
            ->whereIn('id', $matchingIds)
            ->with([
                'employee:id,first_name,last_name',
                'call:id,customer_id,customer_name,caller_number,duration_seconds,started_at,conversation_date,created_at',
                'call.customer:id,name,company_name,phone_number',
            ])
            ->orderByDesc('analyzed_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get([
                'id',
                'call_id',
                'organization_user_id',
                'score',
                'is_evaluable',
                'summary',
                'concerns_json',
                'customer_identity_json',
                'analyzed_at',
            ])
            ->map(function (ConversationAnalysis $analysis) use ($type): array {
                $call = $analysis->call;

                return [
                    'analysis_id' => $analysis->id,
                    'customer' => AnalysisInsightPresenter::customerName($analysis)
                        ?? $call?->customer?->displayName()
                        ?? $call?->caller_number
                        ?? 'مشتری نامشخص',
                    'employee' => $analysis->employee?->full_name ?: '—',
                    'date' => JalaliDate::datetime($call?->occurredAt() ?? $analysis->analyzed_at),
                    'duration_label' => $this->callMetrics->formatDuration((int) ($call?->duration_seconds ?? 0)),
                    'quality_score' => $analysis->isEvaluable() ? $analysis->score : null,
                    'summary' => $analysis->summary,
                    'concerns' => $this->matchingConcernQuotes($analysis, $type),
                ];
            })
            ->all();

        return [
            'total' => count($matchingIds),
            'calls' => $calls,
        ];
    }

    /**
     * Calls in the current filter with the selected sentiment.
     * Only the negative slice of the sentiment chart is opened from the page.
     *
     * @return array{total: int, calls: list<array{
     *     analysis_id: int,
     *     customer: string,
     *     employee: string,
     *     date: string,
     *     duration_label: string,
     *     quality_score: int|null,
     *     summary: string|null
     * }>}
     */
    public function callsForSentiment(AnalysisListFilter $filter, ?string $sentiment, int $limit = 20): array
    {
        $sentiment = is_string($sentiment) ? strtolower(trim($sentiment)) : '';

        if ($sentiment !== AnalysisSentiment::Negative->value) {
            return ['total' => 0, 'calls' => []];
        }

        $allowed = collect($this->sentimentBreakdown($filter))->pluck('key')->all();

        if (! in_array($sentiment, $allowed, true)) {
            return ['total' => 0, 'calls' => []];
        }

        $matchingIds = [];

        foreach ($this->analysisFacts($filter) as $analysis) {
            if ($analysis->sentiment?->value === $sentiment) {
                $matchingIds[] = (int) $analysis->id;
            }
        }

        if ($matchingIds === []) {
            return ['total' => 0, 'calls' => []];
        }

        $calls = ConversationAnalysis::query()
            ->where('organization_id', $filter->organizationId)
            ->whereIn('id', $matchingIds)
            ->with([
                'employee:id,first_name,last_name',
                'call:id,customer_id,customer_name,caller_number,duration_seconds,started_at,conversation_date,created_at',
                'call.customer:id,name,company_name,phone_number',
            ])
            ->orderByDesc('analyzed_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get([
                'id',
                'call_id',
                'organization_user_id',
                'score',
                'is_evaluable',
                'summary',
                'customer_identity_json',
                'analyzed_at',
            ])
            ->map(function (ConversationAnalysis $analysis): array {
                $call = $analysis->call;

                return [
                    'analysis_id' => $analysis->id,
                    'customer' => AnalysisInsightPresenter::customerName($analysis)
                        ?? $call?->customer?->displayName()
                        ?? $call?->caller_number
                        ?? 'مشتری نامشخص',
                    'employee' => $analysis->employee?->full_name ?: '—',
                    'date' => JalaliDate::datetime($call?->occurredAt() ?? $analysis->analyzed_at),
                    'duration_label' => $this->callMetrics->formatDuration((int) ($call?->duration_seconds ?? 0)),
                    'quality_score' => $analysis->isEvaluable() ? $analysis->score : null,
                    'summary' => $analysis->summary,
                ];
            })
            ->all();

        return [
            'total' => count($matchingIds),
            'calls' => $calls,
        ];
    }

    /**
     * Lead, sentiment, and concerns share one read of the analysis rows.
     *
     * @return array{lead: array{high: int, medium: int, low: int, total: int, average_score: float}, sentiment: list<array{key: string, label: string, count: int}>, concerns: list<array{type: string, label: string, count: int}>}
     */
    private function factRollup(AnalysisListFilter $filter): array
    {
        $key = spl_object_id($filter);

        if (isset($this->factRollups[$key])) {
            return $this->factRollups[$key];
        }

        $distribution = ['high' => 0, 'medium' => 0, 'low' => 0];
        $scores = [];
        $sentimentCounts = [];
        $concernCounts = [];

        foreach ($this->analysisFacts($filter) as $analysis) {
            foreach ($analysis->concerns_json ?? [] as $concern) {
                $type = $this->concernTypeOf($concern);

                if ($type === null) {
                    continue;
                }

                $concernCounts[$type] = ($concernCounts[$type] ?? 0) + 1;
            }

            // Match dashboard KPIs: score, lead, and sentiment all roll up from evaluable calls only.
            // Personal / non-evaluable analyses often carry a neutral sentiment that would otherwise
            // pull "رضایت مشتری" away from the employer dashboard card for the same 30-day window.
            if (! $analysis->isEvaluable()) {
                continue;
            }

            if ($analysis->sentiment) {
                $sentimentKey = $analysis->sentiment->value;
                $sentimentCounts[$sentimentKey] = ($sentimentCounts[$sentimentKey] ?? 0) + 1;
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

        return $this->factRollups[$key] = [
            'lead' => [
                'high' => $distribution['high'],
                'medium' => $distribution['medium'],
                'low' => $distribution['low'],
                'total' => array_sum($distribution),
                'average_score' => $scores !== [] ? round(array_sum($scores) / count($scores), 1) : 0,
            ],
            'sentiment' => collect($sentimentCounts)
                ->map(function (int $count, string $sentimentKey): array {
                    $sentiment = AnalysisSentiment::tryFrom($sentimentKey);

                    return [
                        'key' => $sentimentKey,
                        'label' => $sentiment?->label() ?? $sentimentKey,
                        'count' => $count,
                    ];
                })
                ->sortByDesc('count')
                ->values()
                ->all(),
            'concerns' => collect($concernCounts)
                ->map(fn (int $count, string $type) => [
                    'type' => $type,
                    'label' => CustomerPresenter::concernLabel($type),
                    'count' => $count,
                ])
                ->sortByDesc('count')
                ->values()
                ->take(5)
                ->all(),
        ];
    }

    private function concernTypeOf(mixed $concern): ?string
    {
        if (! is_array($concern)) {
            return null;
        }

        return strtolower((string) ($concern['type'] ?? 'other'));
    }

    private function analysisHasConcernType(ConversationAnalysis $analysis, string $type): bool
    {
        foreach ($analysis->concerns_json ?? [] as $concern) {
            if ($this->concernTypeOf($concern) === $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{text: string, severity: string}>
     */
    private function matchingConcernQuotes(ConversationAnalysis $analysis, string $type): array
    {
        $quotes = [];

        foreach ($analysis->concerns_json ?? [] as $concern) {
            if ($this->concernTypeOf($concern) !== $type) {
                continue;
            }

            $text = trim((string) ($concern['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            $severity = strtolower((string) ($concern['severity'] ?? 'medium'));

            if (! in_array($severity, ['low', 'medium', 'high'], true)) {
                $severity = 'medium';
            }

            $quotes[] = [
                'text' => $text,
                'severity' => $severity,
            ];
        }

        return $quotes;
    }

    /** @return list<int> */
    private function holidayWeekdays(AnalysisListFilter $filter): array
    {
        return OrganizationHolidays::weekdays($filter->organizationId);
    }

    private function chartDays(AnalysisListFilter $filter): ChartDayFilter
    {
        return app(ChartHolidayCalendar::class)->forRange(
            $filter->organizationId,
            $filter->from,
            $filter->to,
        );
    }

    private function granularity(AnalysisListFilter $filter): string
    {
        $days = $filter->from->diffInDays($filter->to) + 1;

        return $days > 60 ? 'week' : 'day';
    }

    /** @return Collection<int, ConversationAnalysis> */
    private function analysisFacts(AnalysisListFilter $filter): Collection
    {
        $key = spl_object_id($filter);

        return $this->factsByFilter[$key] ??= $this->analyticsQuery($filter)->get([
            'conversation_analyses.id',
            'conversation_analyses.score',
            'conversation_analyses.is_evaluable',
            'conversation_analyses.sentiment',
            'conversation_analyses.lead_quality_json',
            'conversation_analyses.concerns_json',
        ]);
    }

    /**
     * Daily totals from SQL, rolled up to weeks only when the range is long.
     *
     * @return Collection<int, object>
     */
    private function chartBuckets(AnalysisListFilter $filter, string $granularity): Collection
    {
        $key = spl_object_id($filter);
        $daily = $this->dailyBucketsByFilter[$key] ??= $this->dailyChartBuckets($filter);

        if ($granularity !== 'week') {
            return $daily;
        }

        return $daily
            ->groupBy(fn (object $bucket): string => $this->periodKey(Carbon::parse((string) $bucket->period), 'week'))
            ->map(function (Collection $items, string $period): object {
                return (object) [
                    'period' => $period,
                    'total_count' => (int) $items->sum('total_count'),
                    'scored_count' => (int) $items->sum('scored_count'),
                    'score_sum' => (float) $items->sum('score_sum'),
                ];
            })
            ->sortKeys()
            ->values();
    }

    /** @return Collection<int, object> */
    private function dailyChartBuckets(AnalysisListFilter $filter): Collection
    {
        $driver = DB::connection()->getDriverName();
        $moment = 'COALESCE(calls.conversation_date, calls.started_at, calls.created_at, conversation_analyses.analyzed_at)';
        $day = CompanyWorkCalendar::sqlDayKey($moment, $driver);
        $truth = $driver === 'pgsql' ? 'TRUE' : '1';
        $evaluable = "(conversation_analyses.is_evaluable IS NULL OR conversation_analyses.is_evaluable = $truth) AND conversation_analyses.score > 0";

        return $this->analyticsQuery($filter)
            ->whereNotNull('conversation_analyses.analyzed_at')
            ->selectRaw("$day as period")
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw("SUM(CASE WHEN $evaluable THEN 1 ELSE 0 END) as scored_count")
            ->selectRaw("SUM(CASE WHEN $evaluable THEN conversation_analyses.score ELSE 0 END) as score_sum")
            ->groupByRaw($day)
            ->orderByRaw($day)
            ->get();
    }

    private function bucketPeriod(object $bucket, string $granularity): string
    {
        $period = (string) $bucket->period;

        if ($granularity === 'day') {
            return Carbon::parse($period)->toDateString();
        }

        return $period;
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
            return JalaliDate::isoWeekAxisLabel($key);
        }

        return JalaliDate::monthDay($key);
    }

    private function periodTooltipLabel(string $key, string $granularity): string
    {
        if ($granularity === 'week') {
            return JalaliDate::isoWeekTooltipLabel($key);
        }

        return JalaliDate::monthDayWithWeekday($key);
    }
}
