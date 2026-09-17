<?php

namespace App\Services;

use App\Models\ConversationAnalysis;
use App\Models\OrganizationActivity;
use App\Services\Reports\OrganizationCallMetrics;
use App\Support\JalaliDate;
use Illuminate\Support\Collection;

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

        $grouped = ConversationAnalysis::query()
            ->where('organization_id', $this->organizationId)
            ->where('analyzed_at', '>=', now()->subDays($days))
            ->orderBy('analyzed_at')
            ->get()
            ->groupBy(fn ($a) => $a->analyzed_at->format('Y-m-d'));

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
     *     date: string,
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

        return ConversationAnalysis::query()
            ->where('organization_id', $this->organizationId)
            ->evaluable()
            ->where('analyzed_at', '>=', now()->subDays($days)->startOfDay())
            ->whereNotNull('lead_quality_json')
            ->with([
                'employee:id,first_name,last_name,user_id',
                'call:id,customer_id,customer_name,customer_phone,caller_number,title,category,tags',
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
     * @return array{
     *     analysis_id: int,
     *     customer: string,
     *     phone: ?string,
     *     company: ?string,
     *     date: string,
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
            'date' => JalaliDate::date($analysis->analyzed_at),
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
