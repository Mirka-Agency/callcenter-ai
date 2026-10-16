<?php

namespace App\Application\Crm\Deals\Actions;

use App\Application\Crm\Deals\DealAuthorization;
use App\Domain\Crm\Deals\PipelineStagePalette;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SavePipelineBuilder
{
    public function __construct(
        private DealAuthorization $auth,
        private CreatePipeline $createPipeline,
        private UpdatePipeline $updatePipeline,
        private SetDefaultPipeline $setDefaultPipeline,
        private DeletePipelineStage $deletePipelineStage,
    ) {}

    /**
     * @param  list<array{id?: int|null, name: string, description?: string|null, color: string, win_probability?: int|null, is_won_stage?: bool, is_lost_stage?: bool, stale_enabled?: bool}>  $stages
     */
    public function handle(
        int $organizationId,
        User $actor,
        ?int $pipelineId,
        string $name,
        ?string $description,
        bool $isActive,
        bool $isDefault,
        bool $showWinProbability,
        array $stages,
    ): CrmPipeline {
        $this->auth->authorize($this->auth->canManagePipelines($actor, $organizationId));

        if ($stages === []) {
            throw ValidationException::withMessages([
                'stages' => 'حداقل یک مرحله برای کاریز لازم است.',
            ]);
        }

        $normalizedStages = $this->normalizeStages($stages);

        return DB::transaction(function () use (
            $organizationId,
            $actor,
            $pipelineId,
            $name,
            $description,
            $isActive,
            $isDefault,
            $showWinProbability,
            $normalizedStages,
        ): CrmPipeline {
            if ($pipelineId === null) {
                $pipeline = $this->createPipeline->handle(
                    $organizationId,
                    $actor,
                    $name,
                    $description,
                    false,
                );
            } else {
                $pipeline = CrmPipeline::query()
                    ->where('organization_id', $organizationId)
                    ->whereKey($pipelineId)
                    ->lockForUpdate()
                    ->first();

                if (! $pipeline instanceof CrmPipeline) {
                    abort(404);
                }

                $pipeline = $this->updatePipeline->handle(
                    $pipeline,
                    $actor,
                    $name,
                    $description,
                    $isActive,
                    $showWinProbability,
                );
            }

            $pipeline->update([
                'is_active' => $isActive,
                'show_win_probability' => $showWinProbability,
            ]);

            if ($isDefault && ! $pipeline->is_default) {
                $this->setDefaultPipeline->handle($pipeline->fresh() ?? $pipeline, $actor);
                $pipeline = $pipeline->fresh() ?? $pipeline;
            }

            $this->syncStages($pipeline, $actor, $normalizedStages);

            return $pipeline->fresh('stages') ?? $pipeline;
        });
    }

    /**
     * @param  list<array{id?: int|null, name: string, description?: string|null, color: string, win_probability?: int|null, is_won_stage?: bool, is_lost_stage?: bool, stale_enabled?: bool}>  $stages
     * @return list<array{id: int|null, name: string, description: ?string, color: string, win_probability: ?int, is_won_stage: bool, is_lost_stage: bool, stale_after_days: ?int}>
     */
    private function normalizeStages(array $stages): array
    {
        $normalized = [];

        foreach ($stages as $index => $stage) {
            $name = trim((string) ($stage['name'] ?? ''));

            if ($name === '' || mb_strlen($name) > 80) {
                throw ValidationException::withMessages([
                    "stages.$index.name" => 'عنوان مرحله الزامی است و حداکثر ۸۰ نویسه دارد.',
                ]);
            }

            $description = trim((string) ($stage['description'] ?? ''));
            if (mb_strlen($description) > 2000) {
                throw ValidationException::withMessages([
                    "stages.$index.description" => 'توضیح مرحله حداکثر ۲۰۰۰ نویسه دارد.',
                ]);
            }

            $probability = $stage['win_probability'] ?? null;
            if ($probability !== null && $probability !== '') {
                $probability = (int) $probability;
                if ($probability < 0 || $probability > 100) {
                    throw ValidationException::withMessages([
                        "stages.$index.win_probability" => 'احتمال موفقیت باید بین ۰ تا ۱۰۰ باشد.',
                    ]);
                }
            } else {
                $probability = null;
            }

            $isWon = (bool) ($stage['is_won_stage'] ?? false);
            $isLost = (bool) ($stage['is_lost_stage'] ?? false);
            $staleEnabled = (bool) ($stage['stale_enabled'] ?? false);

            $normalized[] = [
                'id' => isset($stage['id']) && $stage['id'] !== null && $stage['id'] !== ''
                    ? (int) $stage['id']
                    : null,
                'name' => $name,
                'description' => $description !== '' ? $description : null,
                'color' => PipelineStagePalette::normalize((string) ($stage['color'] ?? PipelineStagePalette::ALLOWED[0])),
                'win_probability' => $probability,
                'is_won_stage' => $isWon,
                'is_lost_stage' => $isLost,
                'stale_after_days' => $staleEnabled ? 7 : null,
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array{id: int|null, name: string, description: ?string, color: string, win_probability: ?int, is_won_stage: bool, is_lost_stage: bool, stale_after_days: ?int}>  $stages
     */
    private function syncStages(CrmPipeline $pipeline, User $actor, array $stages): void
    {
        $existing = CrmPipelineStage::query()
            ->where('crm_pipeline_id', $pipeline->id)
            ->where('organization_id', $pipeline->organization_id)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $keptIds = [];

        foreach ($stages as $index => $stageData) {
            $position = $index + 1;
            $payload = [
                'name' => $stageData['name'],
                'description' => $stageData['description'],
                'color' => $stageData['color'],
                'win_probability' => $stageData['win_probability'],
                'position' => $position,
                'is_active' => true,
                'is_won_stage' => $stageData['is_won_stage'],
                'is_lost_stage' => $stageData['is_lost_stage'],
                'stale_after_days' => $stageData['stale_after_days'],
            ];

            if ($stageData['id'] !== null && $existing->has($stageData['id'])) {
                $existing[$stageData['id']]->update($payload);
                $keptIds[] = $stageData['id'];

                continue;
            }

            $created = CrmPipelineStage::query()->create([
                'organization_id' => $pipeline->organization_id,
                'crm_pipeline_id' => $pipeline->id,
                ...$payload,
            ]);
            $keptIds[] = (int) $created->id;
        }

        $toDelete = $existing->keys()->diff($keptIds);

        foreach ($toDelete as $stageId) {
            /** @var CrmPipelineStage $stage */
            $stage = $existing[$stageId];
            $transferId = null;
            $hasDeals = CrmDeal::withTrashed()
                ->where('crm_pipeline_stage_id', $stage->id)
                ->exists();

            if ($hasDeals) {
                $transferId = collect($keptIds)->first();
            }

            $this->deletePipelineStage->handle($stage, $actor, $transferId ? (int) $transferId : null);
        }
    }
}
