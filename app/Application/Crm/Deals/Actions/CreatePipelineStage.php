<?php

namespace App\Application\Crm\Deals\Actions;

use App\Application\Crm\Deals\DealAuthorization;
use App\Application\Crm\Deals\DealTenant;
use App\Domain\Crm\Deals\PipelineStagePalette;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePipelineStage
{
    public function __construct(
        private DealAuthorization $auth,
        private DealTenant $tenant,
    ) {}

    public function handle(CrmPipeline $pipeline, User $actor, string $name, string $color): CrmPipelineStage
    {
        $this->auth->authorize($this->auth->canManagePipelines($actor, (int) $pipeline->organization_id));
        $name = $this->name($name);
        $color = PipelineStagePalette::normalize($color);

        return DB::transaction(function () use ($pipeline, $name, $color): CrmPipelineStage {
            $this->tenant->pipeline((int) $pipeline->organization_id, (int) $pipeline->id);
            $position = (int) CrmPipelineStage::query()
                ->where('crm_pipeline_id', $pipeline->id)
                ->lockForUpdate()
                ->max('position');

            return CrmPipelineStage::query()->create([
                'organization_id' => $pipeline->organization_id,
                'crm_pipeline_id' => $pipeline->id,
                'name' => $name,
                'color' => $color,
                'position' => $position + 1,
                'is_active' => true,
            ]);
        });
    }

    private function name(string $name): string
    {
        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 80) {
            throw ValidationException::withMessages([
                'name' => 'عنوان مرحله الزامی است و حداکثر ۸۰ نویسه دارد.',
            ]);
        }

        return $name;
    }
}
