<?php

namespace App\Application\Crm\Deals\Actions;

use App\Application\Crm\Deals\AnalysisDealEvidence;
use App\Application\Crm\Deals\DealAudit;
use App\Application\Crm\Deals\DealAuthorization;
use App\Application\Crm\Deals\DealTenant;
use App\Domain\Crm\Deals\DealActivityType;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\CrmDeal;
use App\Models\CrmDealCall;
use App\Models\CrmDealContact;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LinkCallToDeal
{
    public function __construct(
        private DealAuthorization $auth,
        private DealTenant $tenant,
        private DealAudit $audit,
    ) {}

    public function handle(CrmDeal $deal, Call $call, ?User $actor, string $source = 'manual', ?ConversationAnalysis $analysis = null, bool $asSystem = false): CrmDealCall
    {
        if (! $asSystem) {
            if (! $actor instanceof User) {
                throw new AuthorizationException('مجوز این عملیات را ندارید.');
            }

            $this->auth->authorize($this->auth->canUpdate($actor, $deal));
        }

        if ((int) $call->organization_id !== (int) $deal->organization_id) {
            throw ValidationException::withMessages([
                'call' => 'تماس به این سازمان تعلق ندارد.',
            ]);
        }

        if ($analysis && (int) $analysis->organization_id !== (int) $deal->organization_id) {
            throw ValidationException::withMessages([
                'analysis' => 'تحلیل به این سازمان تعلق ندارد.',
            ]);
        }

        if ($analysis && $analysis->call_id && (int) $analysis->call_id !== (int) $call->id) {
            throw ValidationException::withMessages([
                'analysis' => 'تحلیل به این تماس تعلق ندارد.',
            ]);
        }

        return DB::transaction(function () use ($deal, $call, $actor, $source, $analysis): CrmDealCall {
            $locked = CrmDeal::query()->whereKey($deal->id)->lockForUpdate()->first();

            if (! $locked instanceof CrmDeal) {
                throw new \RuntimeException('Deal disappeared before linking a call.');
            }

            $existing = CrmDealCall::query()->where('call_id', $call->id)->first();

            if ($existing instanceof CrmDealCall) {
                if ((int) $existing->crm_deal_id === (int) $locked->id) {
                    return $existing;
                }

                throw ValidationException::withMessages([
                    'call' => 'این تماس قبلاً به معامله دیگری متصل شده است.',
                ]);
            }

            if ($analysis) {
                $existingAnalysis = CrmDealCall::query()
                    ->where('conversation_analysis_id', $analysis->id)
                    ->first();

                if ($existingAnalysis instanceof CrmDealCall) {
                    if ((int) $existingAnalysis->crm_deal_id === (int) $locked->id) {
                        return $existingAnalysis;
                    }

                    throw ValidationException::withMessages([
                        'analysis' => 'این تحلیل قبلاً به معامله دیگری متصل شده است.',
                    ]);
                }
            }

            if ($call->customer_id) {
                $this->tenant->customer((int) $locked->organization_id, (int) $call->customer_id);
                $already = CrmDealContact::query()
                    ->where('crm_deal_id', $locked->id)
                    ->where('customer_id', $call->customer_id)
                    ->exists();

                if (! $already) {
                    $hasPrimary = CrmDealContact::query()
                        ->where('crm_deal_id', $locked->id)
                        ->where('is_primary', true)
                        ->exists();

                    CrmDealContact::query()->create([
                        'organization_id' => $locked->organization_id,
                        'crm_deal_id' => $locked->id,
                        'customer_id' => $call->customer_id,
                        'is_primary' => ! $hasPrimary,
                    ]);
                }
            }

            $link = CrmDealCall::query()->create([
                'organization_id' => $locked->organization_id,
                'crm_deal_id' => $locked->id,
                'call_id' => $call->id,
                'conversation_analysis_id' => $analysis?->id,
                'source' => $source,
                'evidence_json' => $analysis ? AnalysisDealEvidence::from($analysis) : null,
                'linked_by_user_id' => $actor?->id,
            ]);

            $locked->update(['last_activity_at' => now()]);
            $this->audit->record(
                $locked,
                DealActivityType::CallLinked,
                $actor?->id,
                $source,
                null,
                ['call_id' => (int) $call->id, 'conversation_analysis_id' => $analysis?->id],
            );

            return $link;
        });
    }
}
