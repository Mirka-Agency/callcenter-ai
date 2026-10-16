<?php

namespace App\Application\Crm\Deals\Actions;

use App\Application\Crm\Deals\AnalysisDealEvidence;
use App\Application\Crm\Deals\DealRelatedMatcher;
use App\Domain\Crm\Deals\DealStatus;
use App\Domain\Crm\Deals\DealSuggestionStatus;
use App\Domain\Crm\Deals\DealSuggestionType;
use App\Models\ConversationAnalysis;
use App\Models\CrmDeal;
use App\Models\CrmDealAiSuggestion;
use App\Models\CrmDealCall;
use App\Models\Customer;
use App\Models\Organization;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class SuggestDealFromAnalysis
{
    public function __construct(
        private LinkCallToDeal $links,
        private DealRelatedMatcher $matcher,
    ) {}

    public function handle(ConversationAnalysis $analysis, int $customerId): ?CrmDealAiSuggestion
    {
        if ($analysis->is_personal || ! $analysis->call_id) {
            return null;
        }

        $organization = Organization::query()->find($analysis->organization_id);

        // Re-check mode at execution time (queued jobs may have been enqueued before a mode change).
        if (! $organization?->hasConfiguredCrmMode() || ! $organization->usesInternalCrm()) {
            return null;
        }

        $customer = Customer::query()->whereKey($customerId)->first();

        if (! $customer instanceof Customer || (int) $customer->organization_id !== (int) $analysis->organization_id) {
            return null;
        }

        try {
            return DB::transaction(function () use ($analysis, $customer): ?CrmDealAiSuggestion {
                $existing = CrmDealAiSuggestion::query()
                    ->where('conversation_analysis_id', $analysis->id)
                    ->lockForUpdate()
                    ->first();

                if ($existing instanceof CrmDealAiSuggestion) {
                    return $existing;
                }

                if (CrmDealCall::query()->where('conversation_analysis_id', $analysis->id)->exists()
                    || CrmDealCall::query()->where('call_id', $analysis->call_id)->exists()
                ) {
                    return null;
                }

                $openDeals = CrmDeal::query()
                    ->where('organization_id', $analysis->organization_id)
                    ->where('status', DealStatus::Open)
                    ->whereHas('contacts', fn ($query) => $query->where('customer_id', $customer->id))
                    ->orderBy('id')
                    ->get();

                $evidence = AnalysisDealEvidence::from($analysis);
                $title = 'فرصت فروش — '.$customer->displayName();
                $call = $analysis->call;

                if (! $call) {
                    return null;
                }

                $ranked = $this->matcher->rank($openDeals, $analysis);
                $clearMatch = $this->matcher->bestClearMatch($openDeals, $analysis);

                if ($clearMatch instanceof CrmDeal) {
                    $this->links->handle($clearMatch, $call, null, 'analysis', $analysis, true);

                    return CrmDealAiSuggestion::query()->create([
                        'organization_id' => $analysis->organization_id,
                        'conversation_analysis_id' => $analysis->id,
                        'customer_id' => $customer->id,
                        'call_id' => $analysis->call_id,
                        'suggestion_type' => DealSuggestionType::LinkCall,
                        'status' => DealSuggestionStatus::AutoLinked,
                        'proposed_title' => null,
                        'payload' => $evidence + [
                            'candidate_deal_ids' => [(int) $clearMatch->id],
                            'ranked_candidates' => $ranked,
                            'reason' => $openDeals->count() === 1 ? 'single_open_deal' : 'clear_topic_match',
                        ],
                        'applied_crm_deal_id' => $clearMatch->id,
                        'resolved_at' => now(),
                    ]);
                }

                if ($openDeals->count() >= 1) {
                    return CrmDealAiSuggestion::query()->create([
                        'organization_id' => $analysis->organization_id,
                        'conversation_analysis_id' => $analysis->id,
                        'customer_id' => $customer->id,
                        'call_id' => $analysis->call_id,
                        'suggestion_type' => DealSuggestionType::ReviewLink,
                        'status' => DealSuggestionStatus::Pending,
                        'proposed_title' => null,
                        'payload' => $evidence + [
                            'candidate_deal_ids' => array_map(fn (array $row) => $row['deal_id'], $ranked),
                            'ranked_candidates' => $ranked,
                            'reason' => 'ambiguous_open_deals',
                            'auto_create' => false,
                        ],
                    ]);
                }

                return CrmDealAiSuggestion::query()->create([
                    'organization_id' => $analysis->organization_id,
                    'conversation_analysis_id' => $analysis->id,
                    'customer_id' => $customer->id,
                    'call_id' => $analysis->call_id,
                    'suggestion_type' => DealSuggestionType::CreateDeal,
                    'status' => DealSuggestionStatus::Pending,
                    'proposed_title' => $title,
                    'payload' => $evidence + [
                        'candidate_deal_ids' => [],
                        'ranked_candidates' => [],
                        'reason' => 'no_open_deal',
                        'auto_create' => false,
                    ],
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            return CrmDealAiSuggestion::query()
                ->where('conversation_analysis_id', $analysis->id)
                ->first();
        }
    }
}
