<?php

namespace App\Application\Crm\Deals;

use App\Domain\Crm\Deals\DealStatus;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\User;

class DealOverview
{
    public function __construct(private DealQuery $deals) {}

    /** @return array<string, mixed> */
    public function summarize(int $organizationId, User $actor, ?int $pipelineId = null): array
    {
        $visible = $this->deals->visible($organizationId, $actor);
        $aging = app(DealAging::class);

        $pipeline = null;

        if ($pipelineId) {
            $pipeline = CrmPipeline::query()
                ->where('organization_id', $organizationId)
                ->whereKey($pipelineId)
                ->first();
        }

        $pipeline ??= CrmPipeline::query()
            ->where('organization_id', $organizationId)
            ->where('is_default', true)
            ->first();

        if ($pipeline instanceof CrmPipeline) {
            $visible->where('crm_pipeline_id', $pipeline->id);
        }

        $open = (clone $visible)->where('status', DealStatus::Open);
        $won = (clone $visible)->where('status', DealStatus::Won)->count();
        $lost = (clone $visible)->where('status', DealStatus::Lost)->count();
        $closed = $won + $lost;

        $stageRows = collect();

        if ($pipeline instanceof CrmPipeline) {
            $stages = CrmPipelineStage::query()
                ->where('crm_pipeline_id', $pipeline->id)
                ->orderBy('position')
                ->get();
            $sums = (clone $open)
                ->select('crm_pipeline_stage_id')
                ->selectRaw('COUNT(*) as deal_count')
                ->selectRaw('COALESCE(SUM(amount_rials), 0) as amount_sum')
                ->groupBy('crm_pipeline_stage_id')
                ->get()
                ->keyBy('crm_pipeline_stage_id');

            $stageRows = $stages->map(function (CrmPipelineStage $stage) use ($sums): array {
                $row = $sums->get($stage->id);

                return [
                    'id' => (int) $stage->id,
                    'name' => $stage->name,
                    'color' => $stage->color,
                    'count' => (int) ($row->deal_count ?? 0),
                    'amount_rials' => (int) ($row->amount_sum ?? 0),
                ];
            });
        }

        return [
            'pipeline' => $pipeline,
            'open_count' => (clone $open)->count(),
            'open_amount_rials' => (int) ((clone $open)->sum('amount_rials') ?? 0),
            'open_without_amount' => (clone $open)->whereNull('amount_rials')->count(),
            'won_count' => $won,
            'lost_count' => $lost,
            'win_rate' => $closed > 0 ? round(($won / $closed) * 100, 1) : null,
            'stale_count' => $aging->filterStaleCollection(
                (clone $open)->with('stage')->limit(500)->get()
            )->count(),
            'stale_inactivity_hint' => 'باز و بدون فعالیت در ۱۴ روز اخیر، یا عبور از آستانه توقف مرحله',
            'stages' => $stageRows,
        ];
    }
}
