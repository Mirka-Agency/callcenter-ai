<?php

namespace App\Application\Crm\Deals\Actions;

use App\Application\Crm\Deals\DealAudit;
use App\Application\Crm\Deals\DealAuthorization;
use App\Application\Crm\Deals\DealTenant;
use App\Domain\Crm\Deals\DealActivityType;
use App\Models\CrmDeal;
use App\Models\CrmPipelineStage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeletePipelineStage
{
    public function __construct(
        private DealAuthorization $auth,
        private DealTenant $tenant,
        private DealAudit $audit,
    ) {}

    public function handle(CrmPipelineStage $stage, User $actor, ?int $transferToStageId = null): void
    {
        $this->auth->authorize($this->auth->canManagePipelines($actor, (int) $stage->organization_id));

        DB::transaction(function () use ($stage, $actor, $transferToStageId): void {
            $locked = CrmPipelineStage::query()->whereKey($stage->id)->lockForUpdate()->first();

            if (! $locked instanceof CrmPipelineStage) {
                return;
            }

            $remaining = CrmPipelineStage::query()
                ->where('crm_pipeline_id', $locked->crm_pipeline_id)
                ->whereKeyNot($locked->id)
                ->count();

            if ($remaining < 1) {
                throw ValidationException::withMessages([
                    'stage' => 'آخرین مرحله پایپ‌لاین را نمی‌توان حذف کرد.',
                ]);
            }

            $deals = CrmDeal::withTrashed()
                ->where('crm_pipeline_stage_id', $locked->id)
                ->lockForUpdate()
                ->get();

            if ($deals->isNotEmpty()) {
                if ($transferToStageId === null) {
                    throw ValidationException::withMessages([
                        'transfer' => 'این مرحله معامله دارد. قبل از حذف، مرحله مقصد را انتخاب کنید.',
                    ]);
                }

                $target = $this->tenant->stage($locked->pipeline, $transferToStageId);

                if ((int) $target->id === (int) $locked->id) {
                    throw ValidationException::withMessages([
                        'transfer' => 'مرحله مقصد باید با مرحله حذف‌شونده فرق داشته باشد.',
                    ]);
                }

                foreach ($deals as $deal) {
                    $previous = $deal->auditSnapshot();
                    $deal->update([
                        'crm_pipeline_stage_id' => $target->id,
                        'last_activity_at' => now(),
                    ]);
                    $this->audit->stage(
                        $deal,
                        $locked,
                        $target,
                        $deal->status->value,
                        $deal->status->value,
                        $actor->id,
                        'manual',
                    );
                    $this->audit->record(
                        $deal,
                        DealActivityType::StageChanged,
                        $actor->id,
                        'manual',
                        $previous,
                        $deal->fresh()->auditSnapshot(),
                    );
                }
            }

            $pipelineId = (int) $locked->crm_pipeline_id;
            $locked->delete();

            $ordered = CrmPipelineStage::query()
                ->where('crm_pipeline_id', $pipelineId)
                ->orderBy('position')
                ->lockForUpdate()
                ->get();
            $offset = 1000 + (int) $ordered->max('position');

            foreach ($ordered as $index => $item) {
                $item->update(['position' => $offset + $index]);
            }

            foreach ($ordered as $index => $item) {
                $item->update(['position' => $index + 1]);
            }
        });
    }
}
