<?php

namespace App\Services;

use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\OrganizationActivity;
use App\Services\Reports\OrganizationCallMetrics;
use App\Support\CallCoachingRules;
use App\Support\CompanyWorkCalendar;
use App\Support\FollowUpDueDateParser;
use App\Support\ForgottenCallbackMatcher;
use App\Support\JalaliDate;
use App\Support\OrganizationHolidays;
use App\Support\PaymentFollowUpSentiment;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class EmployerDashboardAnalytics
{
    public function __construct(
        private int $organizationId,
        private ?OrganizationCallMetrics $callMetrics = null,
    ) {}

    public static function forOrganization(int $organizationId): self
    {
        return new self($organizationId);
    }

    public function cockpit(): array
    {
        $callMetrics = $this->callMetrics ?? app(OrganizationCallMetrics::class);
        $ai = AiPerformanceAnalytics::forOrganization($this->organizationId);
        $overview = $ai->overview();
        $insights = $ai->organizationInsights();

        $followUps = ConversationAnalysis::query()
            ->where('organization_id', $this->organizationId)
            ->whereNotNull('next_actions_json')
            ->whereMonth('analyzed_at', now()->month)
            ->count();

        return [
            'team_average_score' => $insights['team_average'],
            'top_performers' => $insights['top_performers'],
            'needs_attention' => $insights['lowest_performers'],
            'coaching_opportunities' => $insights['coaching_opportunities'],
            'calls_today' => $callMetrics->countToday($this->organizationId),
            'calls_month' => $callMetrics->countThisMonth($this->organizationId),
            'follow_ups_created' => $followUps,
            'total_analyzed' => $overview['total_analyzed'],
            'average_sentiment' => $overview['average_sentiment'],
            'monthly_improvement' => $overview['monthly_improvement'],
        ];
    }

    public function dailyTrend(int $days = 14): array
    {
        return AiPerformanceAnalytics::forOrganization($this->organizationId)
            ->scoreTrend('day', now()->subDays($days), now());
    }

    public function monthlyTrend(int $months = 6): array
    {
        return AiPerformanceAnalytics::forOrganization($this->organizationId)
            ->scoreTrend('month', now()->subMonths($months), now());
    }

    public function sentimentTrend(int $days = 14): array
    {
        $weights = [
            'positive' => 100, 'mixed' => 60, 'neutral' => 50, 'negative' => 20,
        ];

        $holidayWeekdays = OrganizationHolidays::weekdays($this->organizationId);
        $grouped = ConversationAnalysis::query()
            ->where('organization_id', $this->organizationId)
            ->where('analyzed_at', '>=', now()->subDays($days))
            ->with('call:id,conversation_date,started_at,created_at')
            ->orderBy('analyzed_at')
            ->get()
            ->groupBy(fn ($a) => CompanyWorkCalendar::dayKey($a->occurredAt() ?? $a->analyzed_at))
            ->reject(fn ($items, $date) => CompanyWorkCalendar::isHoliday((string) $date, $holidayWeekdays));

        return $grouped->map(fn ($items, $date) => [
            'period' => $date,
            'sentiment' => round($items->avg(fn ($a) => $weights[$a->sentiment->value] ?? 50), 1),
            'count' => $items->count(),
        ])->values()->all();
    }

    public function teamRanking(): array
    {
        return AiPerformanceAnalytics::forOrganization($this->organizationId)
            ->employeePerformance()
            ->sortByDesc('average_score')
            ->values()
            ->take(8)
            ->all();
    }

    public function activityFeed(int $limit = 10): array
    {
        return OrganizationActivity::query()
            ->where('organization_id', $this->organizationId)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn ($a) => [
                'title' => $a->title,
                'description' => $a->description,
                'time' => JalaliDate::ago($a->created_at),
            ])
            ->all();
    }

    /**
     * High-quality recent leads the sales team should follow up.
     *
     * @return list<array{
     *     analysis_id: int,
     *     customer: string,
     *     phone: ?string,
     *     company: ?string,
     *     call_date: string,
     *     sort_date: int,
     *     employee: string,
     *     product: ?string,
     *     lead_score: ?int,
     *     lead_level: ?string,
     *     lead_reason: ?string,
     *     intent: ?string,
     *     purchase_probability: ?int,
     *     follow_up_tags: list<string>,
     *     next_actions: list<string>,
     *     summary: ?string
     * }>
     */
    public function tradingOpportunities(int $days = 30): array
    {
        $days = max(1, min(90, $days));

        $query = ConversationAnalysis::query()
            ->where('organization_id', $this->organizationId)
            ->evaluable()
            ->where('analyzed_at', '>=', now()->subDays($days)->startOfDay())
            ->whereNotNull('lead_quality_json');

        $this->constrainInsightListQuery($query);

        return $query
            ->with([
                'employee:id,first_name,last_name,user_id',
                'call:id,customer_id,customer_name,customer_phone,caller_number,title,category,tags,started_at,conversation_date,created_at',
                'call.customer:id,name,company_name,phone_number',
            ])
            ->latest('analyzed_at')
            ->get([
                'id',
                'call_id',
                'organization_user_id',
                'summary',
                'lead_quality_json',
                'next_actions_json',
                'customer_insights_json',
                'customer_identity_json',
                'operational_insights_json',
                'analyzed_at',
            ])
            ->filter(fn (ConversationAnalysis $analysis) => ($analysis->lead_quality_json['level'] ?? '') === 'high')
            ->map(fn (ConversationAnalysis $analysis) => $this->mapTradingOpportunity($analysis))
            ->values()
            ->all();
    }

    /**
     * Recent customers from satisfied (positive) and dissatisfied (negative) conversations.
     *
     * @return array{
     *     satisfied: list<array{
     *         analysis_id: int,
     *         customer: string,
     *         phone: ?string,
     *         company: ?string,
     *         call_date: string,
     *         employee: string,
     *         summary: ?string,
     *         highlight: ?string
     *     }>,
     *     dissatisfied: list<array{
     *         analysis_id: int,
     *         customer: string,
     *         phone: ?string,
     *         company: ?string,
     *         call_date: string,
     *         employee: string,
     *         summary: ?string,
     *         highlight: ?string
     *     }>
     * }
     */
    public function sentimentCustomers(int $days = 30, int $limit = 10): array
    {
        $days = max(1, min(90, $days));
        $limit = max(1, min(50, $limit));

        return [
            'satisfied' => $this->sentimentCustomerList(AnalysisSentiment::Positive, $days, $limit),
            'dissatisfied' => $this->sentimentCustomerList(AnalysisSentiment::Negative, $days, $limit),
        ];
    }

    /**
     * Phone callbacks the agent promised after a customer request, now overdue with no later outbound call.
     *
     * @return list<array{
     *     analysis_id: int,
     *     customer: string,
     *     phone: ?string,
     *     company: ?string,
     *     employee: string,
     *     forgotten_action: string,
     *     call_date: string,
     *     sort_call_date: int,
     *     days_overdue: int,
     *     forgotten_actions: list<string>,
     *     summary: ?string
     * }>
     */
    public function forgottenFollowUps(int $days = 90): array
    {
        $days = max(1, min(180, $days));

        $sinceKey = $this->insightListsSince()?->getTimestamp() ?? 'none';

        return Cache::remember(
            "dashboard:forgotten:{$this->organizationId}:{$days}:{$sinceKey}",
            120,
            fn () => $this->buildForgottenFollowUps($days),
        );
    }

    /**
     * @return list<array{
     *     analysis_id: int,
     *     customer: string,
     *     phone: ?string,
     *     company: ?string,
     *     employee: string,
     *     forgotten_action: string,
     *     call_date: string,
     *     sort_call_date: int,
     *     days_overdue: int,
     *     forgotten_actions: list<string>,
     *     summary: ?string
     * }>
     */
    private function buildForgottenFollowUps(int $days): array
    {
        $today = now()->startOfDay();

        $query = ConversationAnalysis::query()
            ->where('organization_id', $this->organizationId)
            ->evaluable()
            ->where('analyzed_at', '>=', now()->subDays($days)->startOfDay())
            ->where(function ($query) {
                $query->whereNotNull('next_actions_json')
                    ->orWhereNotNull('operational_insights_json');
            });

        $this->constrainInsightListQuery($query);

        $analyses = $query
            ->with([
                'employee:id,first_name,last_name,user_id',
                'call:id,customer_id,customer_name,customer_phone,caller_number,started_at,conversation_date,created_at',
                'call.customer:id,name,company_name,phone_number',
            ])
            ->latest('analyzed_at')
            ->get([
                'id',
                'call_id',
                'organization_user_id',
                'summary',
                'next_actions_json',
                'weaknesses_json',
                'customer_identity_json',
                'operational_insights_json',
                'analyzed_at',
            ]);

        $laterCalls = $this->indexLaterCalls($this->laterCallsByCustomer($analyses));

        return $analyses
            ->map(fn (ConversationAnalysis $analysis) => $this->mapForgottenFollowUp($analysis, $today, $laterCalls))
            ->filter()
            ->sortByDesc('days_overdue')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ConversationAnalysis>  $analyses
     * @return Collection<int, Call>
     */
    private function laterCallsByCustomer(Collection $analyses): Collection
    {
        $customerIds = $analyses->pluck('call.customer_id')->filter()->unique()->values()->all();
        $phones = $analyses
            ->map(fn (ConversationAnalysis $analysis) => $this->contactPhone($analysis))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($customerIds === [] && $phones === []) {
            return collect();
        }

        $earliest = $analyses
            ->map(fn (ConversationAnalysis $analysis) => $analysis->call?->started_at ?? $analysis->analyzed_at)
            ->filter()
            ->min();

        return Call::query()
            ->where('organization_id', $this->organizationId)
            ->where('direction', 'outbound')
            ->when($earliest, fn ($query) => $query->where(function ($inner) use ($earliest) {
                $inner->where('started_at', '>', $earliest)
                    ->orWhere(function ($created) use ($earliest) {
                        $created->whereNull('started_at')->where('created_at', '>', $earliest);
                    });
            }))
            ->where(function ($query) use ($customerIds, $phones) {
                if ($customerIds !== []) {
                    $query->orWhereIn('customer_id', $customerIds);
                }
                if ($phones !== []) {
                    $query->orWhereIn('customer_phone', $phones)
                        ->orWhereIn('caller_number', $phones)
                        ->orWhereIn('receiver_number', $phones);
                }
            })
            ->get(['id', 'customer_id', 'customer_phone', 'caller_number', 'receiver_number', 'started_at', 'created_at']);
    }

    /**
     * @param  Collection<int, Call>  $laterCalls
     * @return array{by_customer_id: array<int, list<Call>>, by_phone: array<string, list<Call>>}
     */
    private function indexLaterCalls(Collection $laterCalls): array
    {
        $byCustomerId = [];
        $byPhone = [];

        foreach ($laterCalls as $call) {
            if ($call->customer_id) {
                $byCustomerId[(int) $call->customer_id][] = $call;
            }

            foreach ([$call->customer_phone, $call->caller_number, $call->receiver_number] as $candidate) {
                $phone = $this->normalizedPhone($candidate);

                if ($phone !== null) {
                    $byPhone[$phone][] = $call;
                }
            }
        }

        return [
            'by_customer_id' => $byCustomerId,
            'by_phone' => $byPhone,
        ];
    }

    /**
     * @param  array{by_customer_id: array<int, list<Call>>, by_phone: array<string, list<Call>>}  $laterCalls
     * @return array{
     *     analysis_id: int,
     *     customer: string,
     *     phone: ?string,
     *     company: ?string,
     *     employee: string,
     *     forgotten_action: string,
     *     call_date: string,
     *     sort_call_date: int,
     *     days_overdue: int,
     *     forgotten_actions: list<string>,
     *     summary: ?string
     * }|null
     */
    private function mapForgottenFollowUp(ConversationAnalysis $analysis, CarbonInterface $today, array $laterCalls): ?array
    {
        $actions = $this->overdueFollowUpActions($analysis, $today);

        if ($actions === [] || $this->wasFollowedUp($analysis, $laterCalls)) {
            return null;
        }

        $primary = $actions[0];
        $contact = $this->contactSnapshot($analysis);
        $callAt = $this->callOccurredAt($analysis);

        return [
            'analysis_id' => $analysis->id,
            'customer' => $contact['customer'],
            'phone' => $contact['phone'],
            'company' => $contact['company'],
            'employee' => $analysis->employee?->full_name ?? '—',
            'forgotten_action' => $primary['text'],
            'call_date' => JalaliDate::date($callAt),
            'sort_call_date' => $callAt?->getTimestamp() ?? 0,
            'days_overdue' => $primary['days_overdue'],
            'forgotten_actions' => array_values(array_unique(array_column($actions, 'text'))),
            'summary' => $this->nullableText($analysis->summary),
        ];
    }

    /**
     * @return list<array{text: string, due_at: CarbonInterface, days_overdue: int}>
     */
    private function overdueFollowUpActions(ConversationAnalysis $analysis, CarbonInterface $today): array
    {
        $from = $analysis->analyzed_at ?? now();
        $overdue = [];

        foreach ($this->aiFollowUpActions($analysis) as $action) {
            $dueAt = $this->actionDueDate($action['raw'], $action['text'], $from)->startOfDay();

            if ($dueAt->gte($today)) {
                continue;
            }

            $overdue[] = [
                'text' => $action['text'],
                'due_at' => $dueAt,
                'days_overdue' => (int) $dueAt->diffInDays($today),
            ];
        }

        usort($overdue, fn (array $left, array $right) => $right['days_overdue'] <=> $left['days_overdue']);

        return $overdue;
    }

    /**
     * @return list<array{raw: mixed, text: string}>
     */
    private function aiFollowUpActions(ConversationAnalysis $analysis): array
    {
        $suggestions = is_array($analysis->operational_insights_json['follow_up_suggestions'] ?? null)
            ? $analysis->operational_insights_json['follow_up_suggestions']
            : [];
        $nextActions = is_array($analysis->next_actions_json) ? $analysis->next_actions_json : [];
        $seen = [];
        $actions = [];

        foreach ([$suggestions, $nextActions] as $items) {
            foreach ($items as $raw) {
                $text = $this->actionText($raw);
                if ($text === null || isset($seen[$text]) || ! ForgottenCallbackMatcher::matches($text)) {
                    continue;
                }

                $seen[$text] = true;
                $actions[] = ['raw' => $raw, 'text' => $text];
            }
        }

        foreach ($analysis->weaknesses_json ?? [] as $raw) {
            $text = $this->actionText($raw);

            if ($text === null || ! CallCoachingRules::isDeferredRedirectFollowUp($text)) {
                continue;
            }

            $followUp = CallCoachingRules::REDIRECT_FOLLOW_UP;

            if (isset($seen[$followUp])) {
                continue;
            }

            $seen[$followUp] = true;
            $actions[] = ['raw' => $followUp, 'text' => $followUp];
        }

        return $actions;
    }

    private function actionText(mixed $action): ?string
    {
        if (is_string($action)) {
            return $this->nullableText($action);
        }

        if (! is_array($action)) {
            return null;
        }

        return $this->nullableText($action['action'] ?? $action['title'] ?? $action['text'] ?? null);
    }

    private function actionDueDate(mixed $raw, string $text, DateTimeInterface $from): Carbon
    {
        if (is_array($raw)) {
            foreach (['due_at', 'due_date', 'date', 'follow_up_at'] as $key) {
                $value = $raw[$key] ?? null;

                if ($value instanceof DateTimeInterface) {
                    return Carbon::parse($value)->startOfDay();
                }

                if (! is_string($value) || trim($value) === '') {
                    continue;
                }

                $jalali = JalaliDate::toGregorian($value);
                if ($jalali !== null) {
                    return $jalali->startOfDay();
                }

                try {
                    return Carbon::parse($value)->startOfDay();
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return FollowUpDueDateParser::parseOrDefault($text, $from);
    }

    /**
     * @param  array{by_customer_id: array<int, list<Call>>, by_phone: array<string, list<Call>>}  $laterCalls
     */
    private function wasFollowedUp(ConversationAnalysis $analysis, array $laterCalls): bool
    {
        $originalCallId = $analysis->call_id;
        $originalAt = $analysis->call?->started_at ?? $analysis->analyzed_at;
        $customerId = $analysis->call?->customer_id;
        $phone = $this->normalizedPhone($this->contactPhone($analysis));

        if (! $originalAt) {
            return false;
        }

        $candidates = [];
        $seen = [];

        if ($customerId) {
            foreach ($laterCalls['by_customer_id'][(int) $customerId] ?? [] as $call) {
                if (! isset($seen[$call->id])) {
                    $seen[$call->id] = true;
                    $candidates[] = $call;
                }
            }
        }

        if ($phone !== null) {
            foreach ($laterCalls['by_phone'][$phone] ?? [] as $call) {
                if (! isset($seen[$call->id])) {
                    $seen[$call->id] = true;
                    $candidates[] = $call;
                }
            }
        }

        foreach ($candidates as $call) {
            if ($originalCallId && $call->id === $originalCallId) {
                continue;
            }

            $callAt = $call->started_at ?? $call->created_at;
            if ($callAt && $callAt->gt($originalAt)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{
     *     analysis_id: int,
     *     customer: string,
     *     phone: ?string,
     *     company: ?string,
     *     call_date: string,
     *     employee: string,
     *     summary: ?string,
     *     highlight: ?string
     * }>
     */
    private function sentimentCustomerList(AnalysisSentiment $sentiment, int $days, int $limit): array
    {
        $seen = [];

        $query = ConversationAnalysis::query()
            ->where('organization_id', $this->organizationId)
            ->evaluable()
            ->where('sentiment', $sentiment)
            ->where('analyzed_at', '>=', now()->subDays($days)->startOfDay());

        $this->constrainInsightListQuery($query);

        return $query
            ->with([
                'employee:id,first_name,last_name,user_id',
                'call:id,customer_id,customer_name,customer_phone,caller_number,started_at,conversation_date,created_at',
                'call.customer:id,name,company_name,phone_number',
            ])
            ->latest('analyzed_at')
            ->limit($limit * 8)
            ->get([
                'id',
                'call_id',
                'organization_user_id',
                'summary',
                'overall_evaluation',
                'sentiment',
                'strengths_json',
                'concerns_json',
                'customer_insights_json',
                'attention_json',
                'customer_identity_json',
                'analyzed_at',
            ])
            ->reject(fn (ConversationAnalysis $analysis): bool => $sentiment === AnalysisSentiment::Negative
                && PaymentFollowUpSentiment::analysisLooksMisclassified($analysis))
            ->map(fn (ConversationAnalysis $analysis) => $this->mapSentimentCustomer($analysis))
            ->filter(function (array $item) use (&$seen) {
                $key = $item['customer_key'];

                if (isset($seen[$key])) {
                    return false;
                }

                $seen[$key] = true;

                return true;
            })
            ->take($limit)
            ->map(function (array $item) {
                unset($item['customer_key']);

                return $item;
            })
            ->values()
            ->all();
    }

    /**
     * Insight lists ignore historical rows before the reset cutoff.
     * Match on analyzed_at or updated_at so a re-analysis always qualifies.
     *
     * @param  Builder<ConversationAnalysis>  $query
     */
    private function constrainInsightListQuery($query): void
    {
        $since = $this->insightListsSince();

        if ($since === null) {
            return;
        }

        $query->where(function ($inner) use ($since): void {
            $inner->where('analyzed_at', '>=', $since)
                ->orWhere('updated_at', '>=', $since);
        });
    }

    private function insightListsSince(): ?CarbonInterface
    {
        $value = config('dashboard.insight_lists_since');

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value)->utc();
    }

    /**
     * @return array{
     *     analysis_id: int,
     *     customer: string,
     *     phone: ?string,
     *     company: ?string,
     *     call_date: string,
     *     employee: string,
     *     summary: ?string,
     *     highlight: ?string,
     *     customer_key: string
     * }
     */
    private function mapSentimentCustomer(ConversationAnalysis $analysis): array
    {
        $contact = $this->contactSnapshot($analysis);

        return [
            'analysis_id' => $analysis->id,
            'customer' => $contact['customer'],
            'phone' => $contact['phone'],
            'company' => $contact['company'],
            'call_date' => JalaliDate::date($this->callOccurredAt($analysis)),
            'employee' => $analysis->employee?->full_name ?? '—',
            'summary' => $this->nullableText($analysis->summary),
            'highlight' => $analysis->sentiment === AnalysisSentiment::Negative
                ? $this->firstListText($analysis->concerns_json)
                : $this->firstListText($analysis->strengths_json),
            'customer_key' => $this->sentimentCustomerKey($analysis, $contact['phone']),
        ];
    }

    private function sentimentCustomerKey(ConversationAnalysis $analysis, ?string $phone): string
    {
        $customerId = $analysis->call?->customer_id;
        if ($customerId) {
            return 'customer:'.$customerId;
        }

        $normalized = $this->normalizedPhone($phone);
        if ($normalized !== null) {
            return 'phone:'.$normalized;
        }

        return 'analysis:'.$analysis->id;
    }

    private function firstListText(mixed $items): ?string
    {
        foreach (is_array($items) ? $items : [] as $item) {
            $text = $this->actionText($item);

            if ($text !== null) {
                return $text;
            }
        }

        return null;
    }

    /**
     * @return array{customer: string, phone: ?string, company: ?string}
     */
    private function contactSnapshot(ConversationAnalysis $analysis): array
    {
        $call = $analysis->call;
        $customer = $call?->customer;
        $identity = $analysis->customer_identity_json ?? [];
        $phone = $this->contactPhone($analysis);
        $company = $customer?->company_name
            ?: ($identity['company_name'] ?? null);

        return [
            'customer' => $customer?->displayName()
                ?: ($identity['person_name'] ?? null)
                ?: ($call?->customer_name ?: null)
                ?: ($phone ?: '—'),
            'phone' => $this->nullableText($phone),
            'company' => $this->nullableText($company),
        ];
    }

    private function contactPhone(ConversationAnalysis $analysis): ?string
    {
        $call = $analysis->call;
        $identity = $analysis->customer_identity_json ?? [];

        return $this->nullableText(
            $call?->customer?->phone_number
                ?: ($call?->customer_phone ?: null)
                ?: ($identity['phone_number'] ?? null)
                ?: $call?->caller_number
        );
    }

    private function normalizedPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return $digits !== '' ? $digits : null;
    }

    /**
     * @return array{
     *     analysis_id: int,
     *     customer: string,
     *     phone: ?string,
     *     company: ?string,
     *     call_date: string,
     *     sort_date: int,
     *     employee: string,
     *     product: ?string,
     *     lead_score: ?int,
     *     lead_level: ?string,
     *     lead_reason: ?string,
     *     intent: ?string,
     *     purchase_probability: ?int,
     *     follow_up_tags: list<string>,
     *     next_actions: list<string>,
     *     summary: ?string
     * }
     */
    private function mapTradingOpportunity(ConversationAnalysis $analysis): array
    {
        $call = $analysis->call;
        $customer = $call?->customer;
        $identity = $analysis->customer_identity_json ?? [];
        $lead = $analysis->lead_quality_json ?? [];
        $insights = $analysis->customer_insights_json ?? [];
        $operational = $analysis->operational_insights_json ?? [];
        $callAt = $this->callOccurredAt($analysis);

        $leadScore = isset($lead['score']) && is_numeric($lead['score']) ? (int) $lead['score'] : null;
        $purchaseProbability = isset($insights['purchase_probability']) && is_numeric($insights['purchase_probability'])
            ? (int) $insights['purchase_probability']
            : null;
        $intent = trim((string) ($insights['intent'] ?? ''));
        $reason = trim((string) ($lead['reason'] ?? ''));
        $summary = trim((string) ($analysis->summary ?? ''));
        $phone = $customer?->phone_number
            ?: ($call?->customer_phone ?: null)
            ?: ($identity['phone_number'] ?? null)
            ?: $call?->caller_number;
        $company = $customer?->company_name
            ?: ($identity['company_name'] ?? null);

        return [
            'analysis_id' => $analysis->id,
            'customer' => $customer?->displayName()
                ?: ($identity['person_name'] ?? null)
                ?: ($call?->customer_name ?: null)
                ?: ($phone ?: '—'),
            'phone' => $this->nullableText($phone),
            'company' => $this->nullableText($company),
            'call_date' => JalaliDate::date($callAt),
            'sort_date' => $callAt?->getTimestamp() ?? 0,
            'employee' => $analysis->employee?->full_name ?? '—',
            'product' => $this->sellableProduct($call?->title, $call?->category, $intent, $operational['important_keywords'] ?? []),
            'lead_score' => $leadScore,
            'lead_level' => $this->nullableText($lead['level'] ?? null),
            'lead_reason' => $this->nullableText($reason),
            'intent' => $this->nullableText($intent),
            'purchase_probability' => $purchaseProbability,
            'follow_up_tags' => $this->stringList(array_merge(
                is_array($lead['buying_intent_signals'] ?? null) ? $lead['buying_intent_signals'] : [],
                is_array($operational['important_keywords'] ?? null) ? $operational['important_keywords'] : [],
                is_array($call?->tags) ? $call->tags : [],
            ), 6),
            'next_actions' => $this->stringList(array_merge(
                is_array($analysis->next_actions_json) ? $analysis->next_actions_json : [],
                is_array($operational['follow_up_suggestions'] ?? null) ? $operational['follow_up_suggestions'] : [],
            ), 5),
            'summary' => $this->nullableText($summary),
        ];
    }

    private function callOccurredAt(ConversationAnalysis $analysis): ?CarbonInterface
    {
        $call = $analysis->call;

        return $call?->conversation_date
            ?? $call?->started_at
            ?? $call?->created_at
            ?? $analysis->analyzed_at;
    }

    private function sellableProduct(?string $title, ?string $category, string $intent, mixed $keywords): ?string
    {
        $keywords = $this->stringList($keywords, 6);

        foreach ($keywords as $keyword) {
            if ($this->isProductName($keyword)) {
                return $keyword;
            }
        }

        foreach ([$title, $intent] as $source) {
            $fromTitle = $this->productNameFromLabel($source);

            if ($fromTitle !== null) {
                return $fromTitle;
            }
        }

        return null;
    }

    private function isProductName(string $value): bool
    {
        return (bool) preg_match('/بسته|پلن|سرویس|محصول|اشتراک|قرارداد|طرح|پکیج|گارانتی/u', $value);
    }

    private function productNameFromLabel(?string $value): ?string
    {
        $value = $this->nullableText($value);

        if ($value === null) {
            return null;
        }

        $value = explode(' و ', $value)[0];
        $value = preg_replace('/^(معرفی|پیگیری|بررسی|رسیدگی به|فعال‌سازی|تمدید|ارتقای|درخواست)\s+/u', '', $value) ?? $value;
        $value = trim($value);

        if (! $this->isProductName($value)) {
            return null;
        }

        return $this->nullableText($value);
    }

    private function nullableText(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' || $text === '—' ? null : $text;
    }

    /** @return list<string> */
    private function stringList(mixed $items, int $limit = 3): array
    {
        return Collection::make(is_array($items) ? $items : [])
            ->filter(fn ($item) => is_string($item) && trim($item) !== '')
            ->map(fn (string $item) => trim($item))
            ->unique()
            ->take($limit)
            ->values()
            ->all();
    }
}
