<?php

namespace App\Application\Crm\Deals\Actions;

use App\Application\Crm\Deals\DealAudit;
use App\Application\Crm\Deals\DealAuthorization;
use App\Domain\Crm\Deals\DealActivityType;
use App\Domain\Crm\Deals\DealSource;
use App\Domain\Crm\Deals\DealSuggestionStatus;
use App\Domain\Crm\Deals\DealSuggestionType;
use App\Models\CrmDeal;
use App\Models\CrmDealAiSuggestion;
use App\Models\CrmPipelineStage;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptDealSuggestion
{
    public function __construct(
        private DealAuthorization $auth,
        private EnsureDefaultPipeline $pipelines,
        private CreateDeal $createDeal,
        private LinkCallToDeal $links,
        private DealAudit $audit,
    ) {}

    public function handle(CrmDealAiSuggestion $suggestion, User $actor, ?int $dealId = null): CrmDeal
    {
        $this->auth->authorize($this->auth->canCreate($actor, (int) $suggestion->organization_id));

        return DB::transaction(function () use ($suggestion, $actor, $dealId): CrmDeal {
            $locked = CrmDealAiSuggestion::query()->whereKey($suggestion->id)->lockForUpdate()->first();

            if (! $locked instanceof CrmDealAiSuggestion || $locked->status !== DealSuggestionStatus::Pending) {
                throw ValidationException::withMessages([
                    'suggestion' => 'این پیشنهاد دیگر در انتظار بررسی نیست.',
                ]);
            }

            $analysis = $locked->analysis;
            $call = $locked->call;

            if ($locked->suggestion_type === DealSuggestionType::CreateDeal) {
                $pipeline = $this->pipelines->handle((int) $locked->organization_id);
                $stage = CrmPipelineStage::query()
                    ->where('crm_pipeline_id', $pipeline->id)
                    ->where('is_active', true)
                    ->orderBy('position')
                    ->first();

                if (! $stage instanceof CrmPipelineStage) {
                    throw ValidationException::withMessages([
                        'pipeline' => 'پایپ‌لاین فعال مرحله‌ای برای ساخت معامله ندارد.',
                    ]);
                }

                $ownerId = null;

                if ($analysis?->organization_user_id) {
                    $member = OrganizationUser::query()->find($analysis->organization_user_id);

                    if ($member instanceof OrganizationUser
                        && (int) $member->organization_id === (int) $locked->organization_id
                        && $member->is_active
                    ) {
                        $ownerId = (int) $member->id;
                    }
                }
                $deal = $this->createDeal->handle(
                    (int) $locked->organization_id,
                    $actor,
                    [
                        'title' => $locked->proposed_title ?: 'فرصت فروش',
                        'pipeline_id' => $pipeline->id,
                        'stage_id' => $stage->id,
                        'amount_rials' => null,
                        'customer_ids' => $locked->customer_id ? [(int) $locked->customer_id] : [],
                        'primary_customer_id' => $locked->customer_id ? (int) $locked->customer_id : null,
                        'owner_organization_user_id' => $ownerId,
                        'description' => null,
                    ],
                    DealSource::Analysis,
                );
            } else {
                $candidates = array_map('intval', (array) ($locked->payload['candidate_deal_ids'] ?? []));

                if ($dealId === null || ! in_array($dealId, $candidates, true)) {
                    throw ValidationException::withMessages([
                        'deal' => 'معامله انتخاب‌شده در پیشنهاد این تماس نیست.',
                    ]);
                }

                $deal = CrmDeal::query()
                    ->where('organization_id', $locked->organization_id)
                    ->whereKey($dealId)
                    ->first();

                if (! $deal instanceof CrmDeal) {
                    throw ValidationException::withMessages([
                        'deal' => 'معامله پیدا نشد.',
                    ]);
                }

                $this->auth->authorize($this->auth->canUpdate($actor, $deal));
            }

            if ($call) {
                $this->links->handle($deal, $call, $actor, 'analysis', $analysis, false);
            }

            $locked->update([
                'status' => DealSuggestionStatus::Accepted,
                'applied_crm_deal_id' => $deal->id,
                'resolved_by_user_id' => $actor->id,
                'resolved_at' => now(),
            ]);

            $this->audit->record(
                $deal,
                DealActivityType::SuggestionAccepted,
                $actor->id,
                'analysis',
                null,
                ['suggestion_id' => (int) $locked->id],
            );

            return $deal->fresh(['stage', 'primaryContact.customer']) ?? $deal;
        });
    }
}
