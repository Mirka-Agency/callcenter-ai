<?php

namespace App\Application\Crm\Tasks\Actions;

use App\Domain\Crm\Deals\DealStatus;
use App\Domain\Crm\Tasks\TaskDueBasis;
use App\Domain\Crm\Tasks\TaskDuePolicy;
use App\Domain\Crm\Tasks\TaskSuggestionStatus;
use App\Models\ConversationAnalysis;
use App\Models\CrmAiTaskSuggestion;
use App\Models\CrmDeal;
use App\Models\Customer;
use App\Models\Organization;
use App\Support\CustomerNextActionAggregator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SuggestTasksFromAnalysis
{
    public function handle(ConversationAnalysis $analysis, int $customerId): int
    {
        if ($analysis->is_personal || ! $analysis->call_id) {
            return 0;
        }

        $organization = Organization::query()->find($analysis->organization_id);
        if (! $organization?->hasConfiguredCrmMode() || ! $organization->usesInternalCrm()) {
            return 0;
        }

        $customer = Customer::query()->whereKey($customerId)->first();
        if (! $customer instanceof Customer || (int) $customer->organization_id !== (int) $analysis->organization_id) {
            return 0;
        }

        $actions = CustomerNextActionAggregator::prioritized([$analysis]);
        if ($actions === []) {
            $summary = trim((string) $analysis->summary);
            if ($summary === '') {
                return 0;
            }
            // Single free-text next action only when structured list is empty.
            $actions = ['پیگیری: '.Str::limit($summary, 80)];
        }

        $openDeals = CrmDeal::query()
            ->where('organization_id', $analysis->organization_id)
            ->where('status', DealStatus::Open)
            ->whereHas('contacts', fn ($q) => $q->where('customer_id', $customer->id))
            ->orderBy('id')
            ->get();

        // Only attach a deal when uniquely determined — never guess among many.
        $dealId = $openDeals->count() === 1 ? (int) $openDeals->first()->id : null;

        $lead = is_array($analysis->lead_quality_json) ? $analysis->lead_quality_json : [];
        $score = isset($lead['score']) && is_numeric($lead['score']) ? (int) $lead['score'] : null;
        $from = $analysis->analyzed_at ?? $analysis->call?->started_at ?? now();
        $dueAt = TaskDuePolicy::fromLeadScore($score, $from instanceof \Carbon\CarbonInterface ? $from : now());

        $created = 0;

        foreach ($actions as $action) {
            $text = trim((string) $action);
            if ($text === '') {
                continue;
            }

            $fingerprint = hash('sha256', mb_strtolower(preg_replace('/\s+/u', ' ', $text) ?? $text));

            try {
                $suggestion = DB::transaction(function () use ($analysis, $customer, $dealId, $text, $fingerprint, $dueAt): ?CrmAiTaskSuggestion {
                    $existing = CrmAiTaskSuggestion::query()
                        ->where('conversation_analysis_id', $analysis->id)
                        ->where('action_fingerprint', $fingerprint)
                        ->lockForUpdate()
                        ->first();

                    if ($existing instanceof CrmAiTaskSuggestion) {
                        return null;
                    }

                    return CrmAiTaskSuggestion::query()->create([
                        'organization_id' => $analysis->organization_id,
                        'conversation_analysis_id' => $analysis->id,
                        'customer_id' => $customer->id,
                        'crm_deal_id' => $dealId,
                        'call_id' => $analysis->call_id,
                        'action_fingerprint' => $fingerprint,
                        'proposed_title' => mb_substr($text, 0, 180),
                        'proposed_description' => 'پیشنهاد از تحلیل تماس. معامله و موعد را قبل از تأیید بررسی کنید.',
                        'proposed_due_at' => $dueAt,
                        'due_basis' => TaskDueBasis::LeadScore,
                        'status' => TaskSuggestionStatus::Pending,
                        'payload' => [
                            'auto_create' => false,
                            'lead_score' => null,
                            'summary' => $analysis->summary,
                            'action' => $text,
                        ],
                    ]);
                });

                if ($suggestion) {
                    $created++;
                }
            } catch (UniqueConstraintViolationException) {
                // Idempotent retry.
            }
        }

        return $created;
    }
}
