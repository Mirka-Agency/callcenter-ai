<?php

namespace App\Application\Crm\Deals\Actions;

use App\Application\Crm\Deals\DealAudit;
use App\Application\Crm\Deals\DealAuthorization;
use App\Application\Crm\Deals\DealConcurrency;
use App\Application\Crm\Deals\DealHealthScorer;
use App\Application\Crm\Deals\DealState;
use App\Application\Crm\Deals\DealTenant;
use App\Domain\Crm\Deals\DealActivityType;
use App\Domain\Crm\Deals\Events\DealStageChanged;
use App\Models\CrmDeal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeDealStage
{
    public function __construct(
        private DealAuthorization $auth,
        private DealTenant $tenant,
        private DealAudit $audit,
        private DealConcurrency $concurrency,
        private DealHealthScorer $health,
    ) {}

    public function handle(
        CrmDeal $deal,
        User $actor,
        int $stageId,
        ?int $expectedStageId = null,
        bool $asSystem = false,
        mixed $expectedVersion = null,
    ): CrmDeal {
        if (! $asSystem) {
            $this->auth->authorize($this->auth->canChangeStage($actor, $deal));
        } else {
            $this->auth->assertInternalCrm((int) $deal->organization_id);
        }

        return DB::transaction(function () use ($deal, $actor, $stageId, $expectedStageId, $expectedVersion): CrmDeal {
            $locked = CrmDeal::query()->whereKey($deal->id)->lockForUpdate()->first();

            if (! $locked instanceof CrmDeal) {
                throw new \RuntimeException('Deal disappeared before stage change.');
            }

            DealState::assertOpen($locked);
            $this->concurrency->assertExpectedVersion($locked, $expectedVersion);

            if ($expectedStageId !== null && (int) $locked->crm_pipeline_stage_id !== $expectedStageId) {
                throw ValidationException::withMessages([
                    'stage' => 'مرحله معامله هم‌زمان تغییر کرده است. صفحه را تازه کنید.',
                ]);
            }

            $pipeline = $this->tenant->pipeline((int) $locked->organization_id, (int) $locked->crm_pipeline_id);
            $stage = $this->tenant->stage($pipeline, $stageId);

            if (! $stage->is_active) {
                throw ValidationException::withMessages([
                    'stage' => 'مرحله مقصد غیرفعال است.',
                ]);
            }

            if ((int) $locked->crm_pipeline_stage_id === (int) $stage->id) {
                return $locked;
            }

            $from = $locked->stage;
            $previous = $locked->auditSnapshot();
            $this->concurrency->bump($locked, [
                'crm_pipeline_stage_id' => $stage->id,
                'stage_entered_at' => now(),
                'last_activity_at' => now(),
            ]);
            $fresh = $locked->fresh(['stage']) ?? $locked;
            $this->audit->stage($fresh, $from, $stage, $fresh->status->value, $fresh->status->value, $actor->id, 'manual');
            $this->audit->record($fresh, DealActivityType::StageChanged, $actor->id, 'manual', $previous, $fresh->auditSnapshot());
            $this->audit->afterCommit(new DealStageChanged(
                (int) $fresh->organization_id,
                (int) $fresh->id,
                $actor->id,
                'manual',
                $from?->id ? (int) $from->id : null,
                (int) $stage->id,
                $fresh->status->value,
                $fresh->status->value,
            ));
            $this->health->persist($fresh);

            return $fresh->fresh(['stage']) ?? $fresh;
        });
    }
}
