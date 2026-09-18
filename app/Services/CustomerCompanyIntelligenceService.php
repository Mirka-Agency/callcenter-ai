<?php

namespace App\Services;

use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Customer;
use App\Models\CustomerCompany;
use App\Models\OrganizationUser;
use App\Support\CustomerNextActionAggregator;
use App\Support\CustomerPresenter;
use App\Support\JalaliDate;
use Illuminate\Support\Collection;

class CustomerCompanyIntelligenceService
{
    /** @return list<int> */
    public function contactIds(CustomerCompany $company): array
    {
        return Customer::query()
            ->where('customer_company_id', $company->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return Collection<int, OrganizationUser> */
    public function assignedEmployees(CustomerCompany $company): Collection
    {
        $contactIds = $this->contactIds($company);

        if ($contactIds === []) {
            return collect();
        }

        $employeeIds = Call::query()
            ->where('organization_id', $company->organization_id)
            ->whereIn('customer_id', $contactIds)
            ->whereNotNull('organization_user_id')
            ->distinct()
            ->pluck('organization_user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($employeeIds === []) {
            return collect();
        }

        return OrganizationUser::query()
            ->where('organization_id', $company->organization_id)
            ->whereIn('id', $employeeIds)
            ->orderBy('first_name')
            ->get();
    }

    /** @return list<string> */
    public function aggregatedNextActions(CustomerCompany $company): array
    {
        $contactIds = $this->contactIds($company);

        if ($contactIds === []) {
            return [];
        }

        $analyses = ConversationAnalysis::query()
            ->where('organization_id', $company->organization_id)
            ->whereHas('call', fn ($q) => $q->whereIn('customer_id', $contactIds))
            ->latest('analyzed_at')
            ->limit(40)
            ->get();

        return CustomerNextActionAggregator::prioritized($analyses);
    }

    /** @return list<array<string, mixed>> */
    public function timeline(CustomerCompany $company, int $limit = 30): array
    {
        $contactIds = $this->contactIds($company);

        if ($contactIds === []) {
            return [];
        }

        $calls = Call::query()
            ->where('organization_id', $company->organization_id)
            ->whereIn('customer_id', $contactIds)
            ->with(['employee', 'latestAnalysis', 'customer'])
            ->orderByDesc('started_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return $calls->map(function (Call $call) {
            $analysis = $call->latestAnalysis;

            return [
                'call_id' => $call->id,
                'analysis_id' => $analysis?->id,
                'customer_id' => $call->customer_id,
                'customer_name' => $call->customer?->displayName() ?? '—',
                'date' => JalaliDate::datetime($call->started_at ?? $call->created_at),
                'employee_id' => $call->organization_user_id,
                'employee_name' => $call->employee?->full_name ?? '—',
                'duration_seconds' => $call->duration_seconds,
                'score' => $analysis?->score,
                'lead_level' => $analysis?->lead_quality_json['level'] ?? null,
                'summary' => $analysis?->summary,
            ];
        })->all();
    }

    /** @return array<string, mixed> */
    public function profileAnalytics(CustomerCompany $company): array
    {
        $contactIds = $this->contactIds($company);

        if ($contactIds === []) {
            return [
                'average_score' => null,
                'analyzed_calls' => 0,
                'answer_rate' => null,
                'score_series' => [],
                'sentiment_breakdown' => [],
                'concerns' => [],
            ];
        }

        $analyses = ConversationAnalysis::query()
            ->where('organization_id', $company->organization_id)
            ->whereHas('call', fn ($q) => $q->whereIn('customer_id', $contactIds))
            ->orderBy('analyzed_at')
            ->get();

        $calls = Call::query()
            ->where('organization_id', $company->organization_id)
            ->whereIn('customer_id', $contactIds)
            ->get();

        $scores = $analyses->pluck('score')->filter();

        $scoreSeries = $analyses
            ->filter(fn (ConversationAnalysis $analysis) => $analysis->score !== null)
            ->map(fn (ConversationAnalysis $analysis) => [
                'label' => JalaliDate::monthDay($analysis->analyzed_at),
                'score' => (int) $analysis->score,
                'lead_score' => is_numeric($analysis->lead_quality_json['score'] ?? null)
                    ? (int) $analysis->lead_quality_json['score']
                    : null,
            ])
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

        $concernCounts = [];
        foreach ($analyses as $analysis) {
            foreach ($analysis->concerns_json ?? [] as $concern) {
                $type = is_array($concern) ? ($concern['type'] ?? 'other') : 'other';
                $concernCounts[$type] = ($concernCounts[$type] ?? 0) + 1;
            }
        }
        arsort($concernCounts);

        $concerns = collect($concernCounts)
            ->take(5)
            ->map(fn ($count, $type) => [
                'type' => $type,
                'label' => CustomerPresenter::concernLabel($type),
                'count' => $count,
            ])
            ->values()
            ->all();

        $totalCalls = (int) $calls->count();
        $answered = $calls->filter(fn (Call $call) => $call->status === 'completed'
            || $analyses->contains('call_id', $call->id))->count();

        return [
            'average_score' => $scores->isNotEmpty() ? round((float) $scores->avg(), 1) : null,
            'analyzed_calls' => $analyses->count(),
            'answer_rate' => $totalCalls > 0 ? (int) round(($answered / $totalCalls) * 100) : null,
            'score_series' => $scoreSeries,
            'sentiment_breakdown' => $sentimentBreakdown,
            'concerns' => $concerns,
        ];
    }
}
