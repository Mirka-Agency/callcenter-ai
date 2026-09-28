<?php

namespace App\Services;

use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Customer;
use App\Models\CustomerCompany;
use App\Models\OrganizationUser;
use App\Services\Reports\DefinedExtensionCallConstraint;
use App\Support\CompanyName;
use App\Support\CustomerNextActionAggregator;
use App\Support\CustomerPresenter;
use App\Support\CustomerTenantGuard;
use App\Support\JalaliDate;
use App\Support\PersonName;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerIntelligenceService
{
    private const MIN_IDENTITY_CONFIDENCE = 0.5;

    public function __construct(
        private CustomerPhoneResolver $phoneResolver,
        private CustomerCompanyResolver $companyResolver,
        private CustomerCompanyService $companyService,
    ) {}

    public function syncFromAnalysis(ConversationAnalysis $analysis): ?Customer
    {
        if ($analysis->is_personal) {
            return null;
        }

        $analysis->loadMissing(['call']);

        return DB::transaction(function () use ($analysis) {
            $phones = $this->customerPhoneCandidates($analysis);
            $keys = $this->phoneResolver->equivalentKeysForMany($phones);
            $matches = $this->customersMatchingPhones((int) $analysis->organization_id, $keys);
            $linked = $this->linkedCustomer($analysis);

            if ($linked) {
                $matches->push($linked);
            }

            $matches = $matches->unique('id')->values();

            if ($matches->isNotEmpty()) {
                $customer = $this->mergeCustomers($matches);
            } else {
                $customer = $this->customerMatchingIdentity($analysis);
            }

            if (! $customer) {
                $normalized = $this->phoneResolver->normalize($phones[0] ?? null);

                if (! $normalized) {
                    return null;
                }

                $customer = Customer::query()->firstOrCreate(
                    CustomerTenantGuard::tenantPhoneKey($analysis->organization_id, $normalized),
                    [
                        'phone_number' => $phones[0],
                    ],
                );
            }

            if ($analysis->call) {
                $analysis->call->refresh();
                $this->linkCallToCustomer($analysis->call, $customer);
            }

            $this->linkCallsByPhone($customer, $keys);

            $customer->loadMissing('organization');
            $this->mergeIdentity($customer, $analysis, $phones[0] ?? $customer->phone_number);
            $this->refreshAggregates($customer->fresh() ?? $customer);

            return $customer->fresh();
        });
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

    /** @param  list<string>  $extraKeys */
    private function linkCallsByPhone(Customer $customer, array $extraKeys = []): void
    {
        $keys = $this->phoneResolver->equivalentKeysForMany([
            $customer->normalized_phone,
            $customer->phone_number,
            ...$extraKeys,
        ]);

        if ($keys === []) {
            return;
        }

        Call::query()
            ->where('organization_id', $customer->organization_id)
            ->whereNull('customer_id')
            ->where(function ($query) use ($keys) {
                $query->whereIn('normalized_caller_number', $keys)
                    ->orWhereIn('normalized_receiver_number', $keys)
                    ->orWhereIn('normalized_customer_phone', $keys);
            })
            ->update(['customer_id' => $customer->id]);
    }

    /** @return list<string> */
    private function customerPhoneCandidates(ConversationAnalysis $analysis): array
    {
        $call = $analysis->call;
        $candidates = [];

        if ($call) {
            $resolved = $this->phoneResolver->resolveFromCall($call);

            if ($resolved) {
                $candidates[] = $resolved;
            }

            if (filled($call->customer_phone)) {
                $candidates[] = trim((string) $call->customer_phone);
            }
        }

        $identityPhone = trim((string) ($analysis->customer_identity_json['phone_number'] ?? ''));

        if ($identityPhone !== '') {
            $candidates[] = $identityPhone;
        }

        return $this->withoutCounterparty($call, $candidates);
    }

    /** @param  list<string>  $candidates
     * @return list<string>
     */
    private function withoutCounterparty(?Call $call, array $candidates): array
    {
        $candidates = array_values(array_unique(array_filter($candidates, fn (string $phone) => $this->phoneResolver->normalize($phone) !== null)));

        if (! $call || $candidates === []) {
            return $candidates;
        }

        $counterparty = match ($call->direction) {
            'inbound' => $call->receiver_number,
            'outbound' => $call->caller_number,
            default => null,
        };
        $blocked = $this->phoneResolver->equivalentKeys($counterparty);

        if ($blocked === []) {
            return $candidates;
        }

        $filtered = array_values(array_filter($candidates, function (string $phone) use ($blocked) {
            return array_intersect($this->phoneResolver->equivalentKeys($phone), $blocked) === [];
        }));

        return $filtered !== [] ? $filtered : $candidates;
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

        $direct = Customer::query()
            ->where('organization_id', $organizationId)
            ->whereIn('normalized_phone', $keys)
            ->get();

        $fromCalls = Customer::query()
            ->where('organization_id', $organizationId)
            ->whereIn('id', Call::query()
                ->where('organization_id', $organizationId)
                ->whereNotNull('customer_id')
                ->where(function ($query) use ($keys) {
                    $query->whereIn('normalized_caller_number', $keys)
                        ->orWhereIn('normalized_receiver_number', $keys)
                        ->orWhereIn('normalized_customer_phone', $keys);
                })
                ->select('customer_id'))
            ->get();

        return $direct->merge($fromCalls)->unique('id')->values();
    }

    private function linkedCustomer(ConversationAnalysis $analysis): ?Customer
    {
        $customerId = $analysis->call?->customer_id;

        if (! $customerId) {
            return null;
        }

        return Customer::query()
            ->where('organization_id', $analysis->organization_id)
            ->find($customerId);
    }

    private function customerMatchingIdentity(ConversationAnalysis $analysis): ?Customer
    {
        $identity = $analysis->customer_identity_json ?? [];
        $email = mb_strtolower(trim((string) ($identity['email'] ?? '')));

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $byEmail = Customer::query()
                ->where('organization_id', $analysis->organization_id)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->orderByDesc('last_contact_at')
                ->orderBy('id')
                ->get();

            if ($byEmail->isNotEmpty()) {
                return $byEmail->first();
            }
        }

        if ((float) ($identity['confidence'] ?? 0) < self::MIN_IDENTITY_CONFIDENCE) {
            return null;
        }

        $nameKey = PersonName::matchKey((string) ($identity['person_name'] ?? ''));

        if ($nameKey === null) {
            return null;
        }

        $byName = Customer::query()
            ->where('organization_id', $analysis->organization_id)
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->get()
            ->filter(fn (Customer $customer) => PersonName::matchKey($customer->name) === $nameKey)
            ->values();

        return $byName->count() === 1 ? $byName->first() : null;
    }

    /** @param  Collection<int, Customer>  $customers */
    private function mergeCustomers(Collection $customers): Customer
    {
        $keeper = $customers->sortBy('id')->first();

        if (! $keeper instanceof Customer) {
            throw new \RuntimeException('Cannot merge an empty customer set.');
        }

        $duplicates = $customers->where('id', '!=', $keeper->id)->values();

        if ($duplicates->isEmpty()) {
            return $keeper;
        }

        $duplicateIds = $duplicates->pluck('id')->all();

        Call::query()
            ->where('organization_id', $keeper->organization_id)
            ->whereIn('customer_id', $duplicateIds)
            ->update(['customer_id' => $keeper->id]);

        $updates = [];

        foreach (['name', 'company_name', 'email', 'job_title', 'phone_number'] as $field) {
            if (filled($keeper->{$field})) {
                continue;
            }

            $value = $duplicates->pluck($field)->first(fn ($item) => filled($item));

            if ($value) {
                $updates[$field] = $value;
            }
        }

        if (! $keeper->customer_company_id) {
            $companyId = $duplicates->pluck('customer_company_id')->first(fn ($item) => filled($item));

            if ($companyId) {
                $updates['customer_company_id'] = $companyId;
            }
        }

        if ($updates !== []) {
            $keeper->update($updates);
        }

        $companyIds = $duplicates->pluck('customer_company_id')->filter()->unique()->all();
        Customer::query()->whereIn('id', $duplicateIds)->delete();

        foreach (CustomerCompany::query()->whereIn('id', $companyIds)->get() as $company) {
            $this->companyService->refreshAggregates($company);
        }

        return $keeper->fresh() ?? $keeper;
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
        return Call::query()
            ->where('organization_id', $customer->organization_id)
            ->where('customer_id', $customer->id)
            ->count();
    }

    /** @return list<array<string, mixed>> */
    public function timeline(Customer $customer, int $limit = 80): array
    {
        $calls = Call::query()
            ->where('organization_id', $customer->organization_id)
            ->where('customer_id', $customer->id)
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
        $analyses = ConversationAnalysis::query()
            ->where('organization_id', $customer->organization_id)
            ->whereHas('call', fn ($q) => $q->where('customer_id', $customer->id))
            ->latest('analyzed_at')
            ->limit(20)
            ->get();

        return CustomerNextActionAggregator::prioritized($analyses);
    }

    /** @return array<string, mixed> */
    public function profileAnalytics(Customer $customer): array
    {
        $analyses = ConversationAnalysis::query()
            ->where('conversation_analyses.organization_id', $customer->organization_id)
            ->whereHas('call', fn ($q) => $q
                ->where('customer_id', $customer->id)
                ->where('organization_id', $customer->organization_id));

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

    private function mergeIdentity(Customer $customer, ConversationAnalysis $analysis, ?string $phone): void
    {
        $identity = $analysis->customer_identity_json ?? [];
        $confidence = (float) ($identity['confidence'] ?? 0);
        $currentConfidence = (float) ($customer->identity_confidence ?? 0);
        $call = $analysis->call;
        $organizationTitle = (string) ($customer->organization?->title ?? '');

        $incomingCompany = CompanyName::display((string) ($identity['company_name'] ?? ''));
        if ($incomingCompany !== '' && CompanyName::isOwnOrganization($incomingCompany, $organizationTitle)) {
            $incomingCompany = '';
        }

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

            if ($this->shouldReplaceCompanyName($customer->company_name, $incomingCompany, $confidence, $currentConfidence)) {
                $updates['company_name'] = $incomingCompany;
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

        if (
            $incomingCompany === ''
            && $customer->company_name
            && CompanyName::isOwnOrganization($customer->company_name, $organizationTitle)
        ) {
            $updates['company_name'] = null;
            $updates['customer_company_id'] = null;
        }

        $previousCompanyId = $customer->customer_company_id;

        if ($updates !== []) {
            $customer->update($updates);
            $customer->refresh();
        }

        $this->syncCompanyFromIdentity($customer, $incomingCompany);

        if (
            $previousCompanyId
            && $customer->customer_company_id !== $previousCompanyId
        ) {
            $previous = CustomerCompany::query()->find($previousCompanyId);
            if ($previous) {
                $this->companyService->refreshAggregates($previous);
            }
        }
    }

    private function syncCompanyFromIdentity(Customer $customer, string $incomingCompany): void
    {
        $companyName = trim($incomingCompany !== '' ? $incomingCompany : (string) ($customer->company_name ?? ''));

        if ($companyName === '') {
            return;
        }

        $organizationTitle = (string) ($customer->organization?->title ?? '');
        if (CompanyName::isOwnOrganization($companyName, $organizationTitle)) {
            if ($customer->customer_company_id || $customer->company_name) {
                $customer->update([
                    'customer_company_id' => null,
                    'company_name' => null,
                ]);
            }

            return;
        }

        $company = $this->companyResolver->findOrCreate($customer->organization_id, $companyName, excludeOwnOrganization: true);

        if (! $company) {
            if ($customer->customer_company_id || $customer->company_name) {
                $customer->update([
                    'customer_company_id' => null,
                    'company_name' => null,
                ]);
            }

            return;
        }

        if ($customer->customer_company_id !== $company->id || $customer->company_name !== $company->name) {
            $customer->update([
                'customer_company_id' => $company->id,
                'company_name' => $company->name,
            ]);
        }

        $this->companyService->refreshAggregates($company);
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

    private function shouldReplaceCompanyName(?string $current, string $incoming, float $newConfidence, float $currentConfidence): bool
    {
        $incoming = CompanyName::display($incoming);

        if ($incoming === '') {
            return false;
        }

        if ($current === null || $current === '') {
            return true;
        }

        $preferred = CompanyName::preferDisplay($current, $incoming);

        if (CompanyName::matches($current, $incoming)) {
            return $preferred !== $current || $newConfidence > $currentConfidence;
        }

        return $newConfidence > $currentConfidence;
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
