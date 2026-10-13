<?php

namespace App\Application\Customer\Actions;

use App\Models\ConversationAnalysis;
use App\Models\Customer;
use App\Models\CustomerIdentityReview;
use Illuminate\Database\UniqueConstraintViolationException;

class RecordIdentityReview
{
    public function suggestion(
        ConversationAnalysis $analysis,
        ?Customer $customer,
        string $kind,
        string $field,
        ?string $current,
        ?string $suggested,
        ?string $evidence,
        array $context = [],
    ): void {
        $this->store($analysis, $customer, $kind, $field, $current, $suggested, $evidence, $context);
    }

    public function unresolved(ConversationAnalysis $analysis, string $reason): void
    {
        $this->store($analysis, null, 'unresolved_phone', '', null, null, null, [
            'reason' => $reason,
        ]);
    }

    /**
     * @param  list<int>  $customerIds
     */
    public function duplicates(ConversationAnalysis $analysis, array $customerIds): void
    {
        $this->store($analysis, null, 'duplicate_phone', '', null, null, null, [
            'customer_ids' => array_values($customerIds),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function store(
        ConversationAnalysis $analysis,
        ?Customer $customer,
        string $kind,
        string $field,
        ?string $current,
        ?string $suggested,
        ?string $evidence,
        array $context,
    ): void {
        $attributes = [
            'organization_id' => $analysis->organization_id,
            'customer_id' => $customer?->id,
            'call_id' => $analysis->call_id,
            'current_value' => $this->short($current),
            'suggested_value' => $this->short($suggested),
            'evidence' => $this->short($evidence),
            'status' => 'pending',
            'context' => $context === [] ? null : $context,
        ];
        $key = [
            'conversation_analysis_id' => $analysis->id,
            'kind' => $kind,
            'field' => $field,
        ];

        try {
            CustomerIdentityReview::query()->firstOrCreate($key, $attributes);
        } catch (UniqueConstraintViolationException) {
            CustomerIdentityReview::query()->where($key)->first();
        }
    }

    private function short(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, 240);
    }
}
