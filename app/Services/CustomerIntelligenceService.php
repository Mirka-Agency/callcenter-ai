<?php

namespace App\Services;

use App\Application\Customer\Actions\AssignCustomerOwner;
use App\Application\Customer\Actions\RecordIdentityReview;
use App\Application\Customer\CustomerContactMethodMirror;
use App\Application\Customer\ExternalPartyPhoneResolver;
use App\Application\Customer\FieldEnrichmentPolicy;
use App\Domain\Customer\CustomerLifecycleStage;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Customer;
use App\Models\CustomerCompany;
use App\Models\CustomerContactMethod;
use App\Models\OrganizationUser;
use App\Services\CallIntake\CallIntakePolicy;
use App\Services\Reports\DefinedExtensionCallConstraint;
use App\Support\CompanyName;
use App\Support\CustomerFieldProvenance;
use App\Support\CustomerNextActionAggregator;
use App\Support\CustomerPresenter;
use App\Support\CustomerTenantGuard;
use App\Support\JalaliDate;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerIntelligenceService
{
    public function __construct(
        private CustomerPhoneResolver $phoneResolver,
        private CustomerCompanyService $companyService,
        private ExternalPartyPhoneResolver $parties,
        private FieldEnrichmentPolicy $enrichment,
        private RecordIdentityReview $reviews,
        private CustomerContactMethodMirror $contactMethods,
        private AssignCustomerOwner $owners,
    ) {}

    public function syncFromAnalysis(ConversationAnalysis $analysis): ?Customer
    {
        if ($analysis->is_personal) {
            return null;
        }

        $analysis->loadMissing(['call']);

        return DB::transaction(function () use ($analysis) {
            $party = $this->parties->resolve($analysis);

            if (! $party->isValid || $party->normalized === null) {
                $this->reviews->unresolved($analysis, $party->reason);

                return null;
            }

            $keys = $this->phoneResolver->equivalentKeys($party->raw);
            $matches = $this->customersMatchingPhones((int) $analysis->organization_id, $keys);

            if ($matches->count() > 1) {
                $this->reviews->duplicates($analysis, $matches->pluck('id')->map(fn ($id) => (int) $id)->all());

                return null;
            }

            $createdNow = false;

            if ($matches->count() === 1) {
                $customer = $matches->first();
            } else {
                $key = CustomerTenantGuard::tenantPhoneKey($analysis->organization_id, $party->normalized);

                try {
                    $customer = Customer::query()->firstOrCreate($key, [
                        'phone_number' => trim((string) $party->raw),
                        'lifecycle_stage' => CustomerLifecycleStage::NewLead,
                    ]);
                    $createdNow = $customer->wasRecentlyCreated;
                } catch (UniqueConstraintViolationException) {
                    $customer = Customer::query()->where($key)->first();

                    if (! $customer) {
                        throw new \RuntimeException('Customer phone key collided but the existing record was not found.');
                    }
                }
            }

            if (! $customer instanceof Customer) {
                return null;
            }

            if ($analysis->call) {
                $analysis->call->refresh();
                $this->linkCallToCustomer($analysis->call, $customer);
            }

            $customer->loadMissing('organization');
            $this->proposeIdentity($customer, $analysis);
            $fresh = $customer->fresh() ?? $customer;

            if ($createdNow) {
                $fresh = $this->owners->assignFromFirstCall($fresh, $analysis);
            }

            $this->contactMethods->mirrorFromCustomer($fresh);
            $this->refreshAggregates($fresh);

            return $fresh->fresh();
        });
    }

    private function linkCallToCustomer(Call $call, Customer $customer): void
    {
        if ($call->organization_id !== $customer->organization_id) {
            throw new \RuntimeException('Cannot link a call to a customer outside its organization.');
        }

        if ((int) $call->customer_id === (int) $customer->id) {
            return;
        }

        $call->update(['customer_id' => $customer->id]);
    }

    public function relinkCallsByPhone(Customer $customer): void
    {
        // A phone change must not attach another person's older calls.
    }

    /**
     * @param  list<string>  $keys
     * @return Collection<int, Customer>
     */
    private function customersMatchingPhones(int $organizationId, array $keys): Collection
    {
        if ($keys === []) {
            return collect();
        }

        return Customer::query()
            ->where('organization_id', $organizationId)
            ->where(function ($query) use ($organizationId, $keys): void {
                $query->whereIn('normalized_phone', $keys)
                    ->orWhereIn('id', CustomerContactMethod::query()
                        ->where('organization_id', $organizationId)
                        ->where('is_active', true)
                        ->where('verification_status', 'verified')
                        ->whereIn('type', ['mobile', 'phone'])
                        ->whereIn('normalized_value', $keys)
                        ->select('customer_id'));
            })
            ->orderBy('id')
            ->get();
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

    public function timelineCount(Customer $customer): int
    {
        return app(DefinedExtensionCallConstraint::class)->apply(
            Call::query()
                ->where('organization_id', $customer->organization_id)
                ->where('customer_id', $customer->id),
            (int) $customer->organization_id,
        )->count();
    }

    /** @return list<array<string, mixed>> */
    public function timeline(Customer $customer, int $limit = 80): array
    {
        $calls = app(DefinedExtensionCallConstraint::class)->apply(
            Call::query()
                ->where('organization_id', $customer->organization_id)
                ->where('customer_id', $customer->id),
            (int) $customer->organization_id,
        )
            ->with(['employee', 'latestAnalysis'])
            ->orderByDesc('started_at')
            ->orderByDesc('created_at')
            ->limit(max(1, $limit))
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
        $analyses = app(CallIntakePolicy::class)->applyToAnalyses(
            ConversationAnalysis::query()
                ->where('organization_id', $customer->organization_id)
                ->whereHas('call', fn ($q) => $q->where('customer_id', $customer->id)),
            (int) $customer->organization_id,
        )
            ->latest('analyzed_at')
            ->limit(20)
            ->get();

        return CustomerNextActionAggregator::prioritized($analyses);
    }

    /** @return array<string, mixed> */
    public function profileAnalytics(Customer $customer): array
    {
        $analyses = app(CallIntakePolicy::class)->applyToAnalyses(
            ConversationAnalysis::query()
                ->where('conversation_analyses.organization_id', $customer->organization_id)
                ->whereHas('call', fn ($q) => $q
                    ->where('customer_id', $customer->id)
                    ->where('organization_id', $customer->organization_id)),
            (int) $customer->organization_id,
        );

        $stats = (clone $analyses)->toBase();
        $stats->columns = [];
        $row = $stats
            ->selectRaw('COUNT(*) as analyzed_calls')
            ->selectRaw('AVG(CASE WHEN score IS NOT NULL AND score != 0 THEN score END) as average_score')
            ->first();

        $recordedCalls = app(DefinedExtensionCallConstraint::class)->apply(
            Call::query()
                ->where('organization_id', $customer->organization_id)
                ->where('customer_id', $customer->id),
            $customer->organization_id,
        );
        $totalCalls = (clone $recordedCalls)->count();
        $answeredCalls = (clone $recordedCalls)->where('status', 'completed')->count();
        $averageDuration = (clone $recordedCalls)
            ->where('duration_seconds', '>', 0)
            ->avg('duration_seconds');

        $scoreSeries = (clone $analyses)
            ->whereNotNull('conversation_analyses.score')
            ->orderByDesc('conversation_analyses.analyzed_at')
            ->limit(60)
            ->get(['conversation_analyses.analyzed_at', 'conversation_analyses.score', 'conversation_analyses.lead_quality_json'])
            ->reverse()
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

        $sentimentRows = (clone $analyses)->toBase();
        $sentimentRows->columns = [];
        $sentimentBreakdown = $sentimentRows
            ->whereNotNull('sentiment')
            ->select('sentiment')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('sentiment')
            ->get()
            ->map(function (object $row) {
                $sentiment = AnalysisSentiment::tryFrom((string) $row->sentiment);

                return [
                    'key' => (string) $row->sentiment,
                    'label' => $sentiment?->label() ?? (string) $row->sentiment,
                    'count' => (int) $row->aggregate,
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
            'average_score' => $row->average_score !== null ? round((float) $row->average_score, 1) : null,
            'total_calls' => $totalCalls,
            'answered_calls' => $answeredCalls,
            'analyzed_calls' => (int) $row->analyzed_calls,
            'answer_rate' => $totalCalls > 0 ? (int) round(($answeredCalls / $totalCalls) * 100) : null,
            'average_duration_label' => $this->formatDuration(
                $averageDuration !== null ? (int) round((float) $averageDuration) : null,
            ),
            'score_series' => $scoreSeries,
            'sentiment_breakdown' => $sentimentBreakdown,
            'concerns' => $concerns,
        ];
    }

    private function proposeIdentity(Customer $customer, ConversationAnalysis $analysis): void
    {
        $identity = $analysis->customer_identity_json ?? [];
        $confidence = (float) ($identity['confidence'] ?? 0);
        $evidence = (string) ($identity['evidence'] ?? '');
        $sources = $customer->identity_field_sources ?? [];
        $updates = [];

        foreach (['name' => 'person_name', 'email' => 'email', 'job_title' => 'job_title'] as $column => $identityKey) {
            $incoming = trim((string) ($identity[$identityKey] ?? ''));
            $decision = $this->enrichment->decision(
                $customer->{$column},
                CustomerFieldProvenance::isHumanLocked($sources, $column),
                $incoming,
                $evidence,
                $confidence,
            );

            if ($decision === 'fill') {
                $updates[$column] = $incoming;
                $sources = CustomerFieldProvenance::markAi($sources, $column, $confidence);
            }

            if ($decision === 'suggest') {
                $this->reviews->suggestion(
                    $analysis,
                    $customer,
                    'field_suggestion',
                    $column,
                    $customer->{$column},
                    $incoming,
                    $evidence,
                );
            }
        }

        $incomingCompany = CompanyName::display((string) ($identity['company_name'] ?? ''));
        $organizationTitle = (string) ($customer->organization?->title ?? '');
        $companyLocked = CustomerFieldProvenance::companyIsHumanLocked($sources);

        if ($incomingCompany !== '' && ! CompanyName::isOwnOrganization($incomingCompany, $organizationTitle)) {
            $currentCompany = trim((string) ($customer->company_name ?? ''));
            $sameCompany = $currentCompany !== '' && CompanyName::key($currentCompany) === CompanyName::key($incomingCompany);

            if (! $sameCompany) {
                $this->reviews->suggestion(
                    $analysis,
                    $customer,
                    'company_candidate',
                    'company_name',
                    $currentCompany !== '' ? $currentCompany : null,
                    $incomingCompany,
                    $evidence,
                );
            }
        }

        if (
            ! $companyLocked
            && $customer->company_name
            && CompanyName::isOwnOrganization($customer->company_name, $organizationTitle)
        ) {
            $updates['company_name'] = null;
            $updates['customer_company_id'] = null;
        }

        if ($sources !== ($customer->identity_field_sources ?? [])) {
            $updates['identity_field_sources'] = $sources;
        }

        if ($updates !== []) {
            $customer->update($updates);
            $customer->refresh();
        }

        $this->suggestSpokenPhone($customer, $analysis, $identity);
    }

    /** @param  array<string, mixed>  $identity */
    private function suggestSpokenPhone(Customer $customer, ConversationAnalysis $analysis, array $identity): void
    {
        $spoken = trim((string) ($identity['phone_number'] ?? ''));
        $inspection = $this->phoneResolver->inspect($spoken);

        if (! $inspection->isValid || $inspection->normalized === null) {
            return;
        }

        $keys = $this->phoneResolver->equivalentKeys($spoken);
        $alreadyKnown = in_array($customer->normalized_phone, $keys, true)
            || CustomerContactMethod::query()
                ->where('customer_id', $customer->id)
                ->where('is_active', true)
                ->whereIn('type', ['mobile', 'phone'])
                ->whereIn('normalized_value', $keys)
                ->exists();

        if ($alreadyKnown) {
            return;
        }

        $this->reviews->suggestion(
            $analysis,
            $customer,
            'phone_candidate',
            'phone_number',
            $customer->phone_number,
            $spoken,
            (string) ($identity['evidence'] ?? ''),
        );
    }

    private function refreshAggregates(Customer $customer): void
    {
        $calls = app(DefinedExtensionCallConstraint::class)->apply(
            Call::query()
                ->where('organization_id', $customer->organization_id)
                ->where('customer_id', $customer->id),
            $customer->organization_id,
        )
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

        if ($customer->customer_company_id) {
            $company = CustomerCompany::query()->find($customer->customer_company_id);

            if ($company) {
                $this->companyService->refreshAggregates($company);
            }
        }
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
