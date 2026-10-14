<?php

namespace App\Application\Crm\Deals\Actions;

use App\Application\Crm\Deals\DealAuthorization;
use App\Domain\Crm\Deals\PipelineStagePalette;
use App\Models\CrmPipelineStage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdatePipelineStage
{
    public function __construct(private DealAuthorization $auth) {}

    public function handle(CrmPipelineStage $stage, User $actor, string $name, string $color, bool $isActive): CrmPipelineStage
    {
        $this->auth->authorize($this->auth->canManagePipelines($actor, (int) $stage->organization_id));
        $name = trim($name);
        $color = PipelineStagePalette::normalize($color);

        if ($name === '' || mb_strlen($name) > 80) {
            throw ValidationException::withMessages([
                'name' => 'عنوان مرحله الزامی است و حداکثر ۸۰ نویسه دارد.',
            ]);
        }

        return DB::transaction(function () use ($stage, $name, $color, $isActive): CrmPipelineStage {
            $locked = CrmPipelineStage::query()->whereKey($stage->id)->lockForUpdate()->first();

            if (! $locked instanceof CrmPipelineStage) {
                throw new \RuntimeException('Stage disappeared before update.');
            }

            if (! $isActive) {
                $otherActive = CrmPipelineStage::query()
                    ->where('crm_pipeline_id', $locked->crm_pipeline_id)
                    ->whereKeyNot($locked->id)
                    ->where('is_active', true)
                    ->exists();

                if (! $otherActive) {
                    throw ValidationException::withMessages([
                        'is_active' => 'حداقل یک مرحله فعال باید در پایپ‌لاین بماند.',
                    ]);
                }
            }

            $locked->update([
                'name' => $name,
                'color' => $color,
                'is_active' => $isActive,
            ]);

            return $locked;
        });
    }
}
