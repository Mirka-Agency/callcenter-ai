<?php

namespace App\Services;

use App\Models\Call;
use App\Models\Customer;
use App\Models\CustomerCompany;
use App\Services\Reports\DefinedExtensionCallConstraint;
use Illuminate\Support\Collection;

class CustomerCompanyService
{
    public function refreshAggregates(CustomerCompany $company): CustomerCompany
    {
        $contacts = Customer::query()
            ->where('customer_company_id', $company->id)
            ->orderBy('last_contact_at')
            ->get();

        if ($contacts->isEmpty()) {
            $company->update([
                'contacts_count' => 0,
                'total_calls' => 0,
                'latest_lead_score' => null,
                'latest_lead_level' => null,
                'conversation_trend' => null,
                'recommended_next_action' => null,
                'first_contact_at' => null,
                'last_contact_at' => null,
            ]);

            return $company->fresh();
        }

        $latestContact = $contacts
            ->sortByDesc(fn (Customer $contact) => $contact->last_contact_at?->timestamp ?? 0)
            ->first();

        $nextActions = $contacts
            ->pluck('recommended_next_action')
            ->filter()
            ->unique()
            ->values();

        $company->update([
            'contacts_count' => $contacts->count(),
            'total_calls' => (int) $contacts->sum('total_calls'),
            'latest_lead_score' => $latestContact?->latest_lead_score,
            'latest_lead_level' => $latestContact?->latest_lead_level,
            'conversation_trend' => $this->dominantTrend($contacts),
            'recommended_next_action' => $nextActions->first(),
            'first_contact_at' => $contacts->min('first_contact_at'),
            'last_contact_at' => $contacts->max('last_contact_at'),
        ]);

        return $company->fresh();
    }

    /** @return list<array<string, mixed>> */
    public function summaryStats(CustomerCompany $company): array
    {
        $contacts = $company->contacts()->get();
        $contactIds = $contacts->pluck('id');
        $totalCalls = $contactIds->isEmpty()
            ? 0
            : app(DefinedExtensionCallConstraint::class)->apply(
                Call::query()
                    ->where('organization_id', $company->organization_id)
                    ->whereIn('customer_id', $contactIds),
                $company->organization_id,
            )->count();

        return [
            'contacts' => $contacts->count(),
            'total_calls' => $totalCalls,
            'analyzed_calls' => $totalCalls,
            'high_lead_contacts' => $contacts->where('latest_lead_level', 'high')->count(),
        ];
    }

    /** @param  Collection<int, Customer>  $contacts */
    private function dominantTrend(Collection $contacts): ?string
    {
        $trends = $contacts->pluck('conversation_trend')->filter()->countBy();

        if ($trends->isEmpty()) {
            return null;
        }

        return $trends->sortDesc()->keys()->first();
    }
}
