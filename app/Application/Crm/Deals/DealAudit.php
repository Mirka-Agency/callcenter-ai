<?php

namespace App\Application\Crm\Deals;

use App\Domain\Crm\Deals\DealActivityType;
use App\Models\CrmDeal;
use App\Models\CrmDealActivity;
use App\Models\CrmDealStageHistory;
use App\Models\CrmPipelineStage;
use Illuminate\Support\Facades\DB;

class DealAudit
{
    /**
     * @param  array<string, mixed>|null  $previous
     * @param  array<string, mixed>|null  $next
     */
    public function record(
        CrmDeal $deal,
        DealActivityType $type,
        ?int $actorUserId,
        string $source,
        ?array $previous = null,
        ?array $next = null,
    ): void {
        CrmDealActivity::query()->create([
            'organization_id' => $deal->organization_id,
            'crm_deal_id' => $deal->id,
            'type' => $type,
            'actor_user_id' => $actorUserId,
            'source' => $source,
            'previous_json' => $previous,
            'next_json' => $next,
        ]);
    }

    public function stage(
        CrmDeal $deal,
        ?CrmPipelineStage $fromStage,
        ?CrmPipelineStage $toStage,
        ?string $fromStatus,
        string $toStatus,
        ?int $actorUserId,
        string $source,
    ): void {
        CrmDealStageHistory::query()->create([
            'organization_id' => $deal->organization_id,
            'crm_deal_id' => $deal->id,
            'from_stage_id' => $fromStage?->id,
            'to_stage_id' => $toStage?->id,
            'from_stage_name' => $fromStage?->name,
            'to_stage_name' => $toStage?->name,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_user_id' => $actorUserId,
            'source' => $source,
            'changed_at' => now(),
        ]);
    }

    public function afterCommit(object $event): void
    {
        DB::afterCommit(static function () use ($event): void {
            event($event);
        });
    }
}
