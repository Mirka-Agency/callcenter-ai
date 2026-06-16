<?php

namespace App\Services;

use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Customer;
use App\Models\OrganizationUser;
use App\Support\CustomerPresenter;
use App\Support\CustomerTenantGuard;
use App\Support\JalaliDate;
use Illuminate\Support\Collection;

class CustomerIntelligenceService
{
    private const MIN_IDENTITY_CONFIDENCE = 0.5;

    public function __construct(
        private CustomerPhoneResolver $phoneResolver,
    ) {}

    public function syncFromAnalysis(ConversationAnalysis $analysis): ?Customer
    {
        $analysis->loadMissing(['call']);

        $phone = $this->phoneResolver->resolveFromAnalysis($analysis);
        $normalized = $this->phoneResolver->normalize($phone);

        if (! $normalized) {
            return null;
        }

        $customer = Customer::query()->firstOrCreate(
            CustomerTenantGuard::tenantPhoneKey($analysis->organization_id, $normalized),
            [
                'phone_number' => $phone,
            ],
        );

        if ($analysis->call) {
            $this->linkCallToCustomer($analysis->call, $customer);
        }

        $this->linkCallsByPhone($customer);

        $this->mergeIdentity($customer, $analysis, $phone);
        $this->refreshAggregates($customer);

        return $customer->fresh();
    }

    private function linkCallToCustomer(Call $call, Customer $customer): void
    {
        CustomerTenantGuard::assertCanLinkCallToCustomer($call, $customer);

        if ($call->customer_id === $customer->id) {
            return;
        }

        $call->update(['customer_id' => $customer->id]);
    }

    public function relinkCallsByPhone(Customer $customer): void
    {
        $this->linkCallsByPhone($customer);
    }

    private function linkCallsByPhone(Customer $customer): void
    {
        Call::query()
            ->where('organization_id', $customer->organization_id)
            ->whereNull('customer_id')
            ->where(function ($query) use ($customer) {
                $query->whereRaw(
                    "REPLACE(REPLACE(REPLACE(caller_number, '+', ''), '-', ''), ' ', '') = ?",
                    [$customer->normalized_phone],
                )->orWhereRaw(
                    "REPLACE(REPLACE(REPLACE(receiver_number, '+', ''), '-', ''), ' ', '') = ?",
                    [$customer->normalized_phone],
                )->orWhereRaw(
                    "REPLACE(REPLACE(REPLACE(customer_phone, '+', ''), '-', ''), ' ', '') = ?",
                    [$customer->normalized_phone],
                );
            })
            ->update(['customer_id' => $customer->id]);
    }

    /** @return list<int> */
    public function assignedEmployeeIds(Customer $customer): array
    {
        return Call::query()
            ->where('organization_id', $customer->organization_id)
            ->where('customer_id', $customer->id)
            ->whereNotNull('organization_user_id')
            ->distinct()
            ->pluck('organization_user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return Collection<int, OrganizationUser> */
    public function assignedEmployees(Customer $customer): Collection
    {
        $ids = $this->assignedEmployeeIds($customer);

        if ($ids === []) {
            return collect();
        }

        return OrganizationUser::query()
            ->where('organization_id', $customer->organization_id)
            ->whereIn('id', $ids)
            ->orderBy('first_name')
            ->get();
    }

    /** @return list<array<string, mixed>> */
    public function timeline(Customer $customer): array
    {
        $calls = Call::query()
            ->where('organization_id', $customer->organization_id)
            ->where('customer_id', $customer->id)
            ->with(['employee', 'latestAnalysis'])
            ->orderByDesc('started_at')
            ->orderByDesc('created_at')
            ->get();

        return $calls->map(function (Call $call) {
            $analysis = $call->latestAnalysis;

            return [
                'call_id' => $call->id,
                'analysis_id' => $analysis?->id,
                'date' => JalaliDate::datetime($call->started_at ?? $call->created_at),
                'employee_id' => $call->organization_user_id,
                'employee_name' => $call->employee?->full_name ?? '—',
                'duration_seconds' => $call->duration_seconds,
                'duration_label' => $this->formatDuration($call->duration_seconds),
                'score' => $analysis?->score,
                'lead_level' => $analysis?->lead_quality_json['level'] ?? null,
                'lead_score' => $analysis?->lead_quality_json['score'] ?? null,
                'sentiment' => $analysis?->sentiment?->label(),
                'summary' => $analysis?->summary,
                'concerns' => $analysis?->concerns_json ?? [],
                'next_actions' => $analysis?->next_actions_json ?? [],
            ];
        })->all();
    }

    /** @return list<string> */
    public function aggregatedNextActions(Customer $customer): array
    {
        $actions = [];

        ConversationAnalysis::query()
            ->where('organization_id', $customer->organization_id)
            ->whereHas('call', fn ($q) => $q->where('customer_id', $customer->id))
            ->latest('analyzed_at')
            ->limit(20)
            ->get()
            ->each(function (ConversationAnalysis $analysis) use (&$actions): void {
                foreach ($analysis->next_actions_json ?? [] as $action) {
                    $text = is_string($action) ? $action : ($action['action'] ?? $action['title'] ?? null);
                    if ($text) {
                        $actions[] = $text;
                    }
                }
                foreach ($analysis->operational_insights_json['follow_up_suggestions'] ?? [] as $suggestion) {
                    $text = is_string($suggestion) ? $suggestion : ($suggestion['action'] ?? null);
                    if ($text) {
                        $actions[] = $text;
                    }
                }
            });

        return array_values(array_unique(array_filter($actions)));
    }

    /** @return array<string, mixed> */
    public function profileAnalytics(Customer $customer): array
    {
        $analyses = ConversationAnalysis::query()
            ->where('organization_id', $customer->organization_id)
            ->whereHas('call', fn ($q) => $q->where('customer_id', $customer->id))
            ->orderBy('analyzed_at')
            ->get();

        $calls = Call::query()
            ->where('organization_id', $customer->organization_id)
            ->where('customer_id', $customer->id)
            ->get();

        $scores = $analyses->pluck('score')->filter();
        $durations = $calls->pluck('duration_seconds')->filter(fn (?int $seconds) => $seconds && $seconds > 0);

        $scoreSeries = $analyses
            ->filter(fn (ConversationAnalysis $analysis) => $analysis->score !== null)
            ->map(function (ConversationAnalysis $analysis) {
                $leadScore = $analysis->lead_quality_json['score'] ?? null;

                return [
                    'label' => JalaliDate::monthDay($analysis->analyzed_at),
                    'score' => (int) $analysis->score,
                    'lead_score' => is_numeric($leadScore) ? (int) $leadScore : null,
                ];
            })
            ->values()
            ->all();

        $sentimentCounts = [];
        foreach ($analyses as $analysis) {
            if (! $analysis->sentiment) {
                continue;
            }

            $key = $analysis->sentiment->value;
            $sentimentCounts[$key] = ($sentimentCounts[$key] ?? 0) + 1;
        }

        $sentimentBreakdown = collect($sentimentCounts)
            ->map(function (int $count, string $key) {
                $sentiment = AnalysisSentiment::tryFrom($key);

                return [
                    'key' => $key,
                    'label' => $sentiment?->label() ?? $key,
                    'count' => $count,
                ];
            })
            ->values()
            ->all();

        $concerns = collect($customer->common_concerns_json ?? [])
            ->map(fn (array $concern) => [
                'type' => $concern['type'] ?? 'other',
                'label' => CustomerPresenter::concernLabel($concern['type'] ?? 'other'),
                'count' => (int) ($concern['count'] ?? 0),
            ])
            ->values()
            ->all();

        return [
            'average_score' => $scores->isNotEmpty() ? round((float) $scores->avg(), 1) : null,
            'analyzed_calls' => $analyses->count(),
            'answer_rate' => CustomerPresenter::answerRate($customer),
            'average_duration_label' => $this->formatDuration(
                $durations->isNotEmpty() ? (int) round($durations->avg()) : null,
            ),
            'score_series' => $scoreSeries,
            'sentiment_breakdown' => $sentimentBreakdown,
            'concerns' => $concerns,
        ];
    }

    private function mergeIdentity(Customer $customer, ConversationAnalysis $analysis, ?string $phone): void
    {
        $identity = $analysis->customer_identity_json ?? [];
        $confidence = (float) ($identity['confidence'] ?? 0);
        $currentConfidence = (float) ($customer->identity_confidence ?? 0);
        $call = $analysis->call;

        $updates = [];

        if ($phone && ! $customer->phone_number) {
            $updates['phone_number'] = $phone;
        }

        if ($call?->customer_name && ! $customer->name) {
            $updates['name'] = $call->customer_name;
        }

        if ($confidence >= self::MIN_IDENTITY_CONFIDENCE || $currentConfidence < self::MIN_IDENTITY_CONFIDENCE) {
            if ($this->shouldReplaceField($customer->name, $identity['person_name'] ?? '', $confidence, $currentConfidence)) {
                $updates['name'] = trim((string) $identity['person_name']);
            }

            if ($this->shouldReplaceField($customer->company_name, $identity['company_name'] ?? '', $confidence, $currentConfidence)) {
                $updates['company_name'] = trim((string) $identity['company_name']);
            }

            if ($this->shouldReplaceField($customer->email, $identity['email'] ?? '', $confidence, $currentConfidence)) {
                $updates['email'] = trim((string) $identity['email']);
            }

            if ($this->shouldReplaceField($customer->job_title, $identity['job_title'] ?? '', $confidence, $currentConfidence)) {
                $updates['job_title'] = trim((string) $identity['job_title']);
            }

            if ($confidence > $currentConfidence) {
                $updates['identity_confidence'] = $confidence;
            }
        }

        if ($updates !== []) {
            $customer->update($updates);
        }
    }

    private function shouldReplaceField(?string $current, string $incoming, float $newConfidence, float $currentConfidence): bool
    {
        $incoming = trim($incoming);

        if ($incoming === '') {
            return false;
        }

        if ($current === null || $current === '') {
            return true;
        }

        return $newConfidence > $currentConfidence;
    }

    private function refreshAggregates(Customer $customer): void
    {
        $calls = Call::query()
            ->where('organization_id', $customer->organization_id)
            ->where('customer_id', $customer->id)
            ->orderBy('started_at')
            ->orderBy('created_at')
            ->get();

        $analyses = ConversationAnalysis::query()
            ->where('organization_id', $customer->organization_id)
            ->whereIn('call_id', $calls->pluck('id'))
            ->orderBy('analyzed_at')
            ->get();

        $firstContact = $calls->first()?->started_at ?? $calls->first()?->created_at;
        $lastContact = $calls->last()?->started_at ?? $calls->last()?->created_at
            ?? $analyses->last()?->analyzed_at;

        $answered = $calls->filter(fn (Call $call) => $call->status === 'completed' || $analyses->contains('call_id', $call->id))->count();

        $latestAnalysis = $analyses->last();
        $leadQuality = $latestAnalysis?->lead_quality_json ?? [];
        $customerInsights = $latestAnalysis?->customer_insights_json ?? [];

        $concernCounts = [];
        foreach ($analyses as $analysis) {
            foreach ($analysis->concerns_json ?? [] as $concern) {
                $type = is_array($concern) ? ($concern['type'] ?? 'other') : 'other';
                $concernCounts[$type] = ($concernCounts[$type] ?? 0) + 1;
            }
        }
        arsort($concernCounts);

        $scores = $analyses->pluck('score')->filter()->values();
        $trend = $this->detectTrend($scores);

        $nextActions = $this->aggregatedNextActions($customer);

        $customer->update([
            'first_contact_at' => $firstContact,
            'last_contact_at' => $lastContact,
            'total_calls' => $calls->count(),
            'total_answered_calls' => $answered,
            'latest_lead_score' => isset($leadQuality['score']) ? (int) $leadQuality['score'] : null,
            'latest_lead_level' => $leadQuality['level'] ?? null,
            'common_concerns_json' => collect($concernCounts)->map(fn ($count, $type) => [
                'type' => $type,
                'count' => $count,
            ])->values()->take(5)->all(),
            'purchase_intent' => $customerInsights['purchase_probability'] ?? $customerInsights['intent'] ?? null,
            'conversation_trend' => $trend,
            'recommended_next_action' => $nextActions[0] ?? null,
        ]);
    }

    private function detectTrend(Collection $scores): ?string
    {
        if ($scores->count() < 2) {
            return null;
        }

        $half = (int) ceil($scores->count() / 2);
        $firstHalf = $scores->take($half)->avg();
        $secondHalf = $scores->skip($half)->avg();

        if ($secondHalf > $firstHalf + 3) {
            return 'improving';
        }

        if ($secondHalf < $firstHalf - 3) {
            return 'declining';
        }

        return 'stable';
    }

    private function formatDuration(?int $seconds): string
    {
        if (! $seconds || $seconds <= 0) {
            return '—';
        }

        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
