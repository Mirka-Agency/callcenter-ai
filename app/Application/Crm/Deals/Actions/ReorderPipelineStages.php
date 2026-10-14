<?php

namespace App\Application\Crm\Deals\Actions;

use App\Application\Crm\Deals\DealAuthorization;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReorderPipelineStages
{
    public function __construct(private DealAuthorization $auth) {}

    /** @param  list<int>  $stageIds */
    public function handle(CrmPipeline $pipeline, User $actor, array $stageIds): CrmPipeline
    {
        $this->auth->authorize($this->auth->canManagePipelines($actor, (int) $pipeline->organization_id));

        return DB::transaction(function () use ($pipeline, $stageIds): CrmPipeline {
            $stages = CrmPipelineStage::query()
                ->where('crm_pipeline_id', $pipeline->id)
                ->where('organization_id', $pipeline->organization_id)
                ->lockForUpdate()
                ->get();

            $currentIds = $stages->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $incoming = collect($stageIds)->map(fn ($id) => (int) $id)->values();
            $sortedIncoming = $incoming->sort()->values()->all();

            if ($incoming->unique()->count() !== $incoming->count() || $sortedIncoming !== $currentIds) {
                throw ValidationException::withMessages([
                    'stages' => 'ترتیب مراحل با مراحل همین پایپ‌لاین هم‌خوان نیست.',
                ]);
            }

            $byId = $stages->keyBy('id');
            $offset = 1000 + (int) $stages->max('position');

            foreach ($incoming as $index => $stageId) {
                $byId[$stageId]->update(['position' => $offset + $index]);
            }

            foreach ($incoming as $index => $stageId) {
                $byId[$stageId]->update(['position' => $index + 1]);
            }

            return $pipeline->fresh('stages') ?? $pipeline;
        });
    }
}
