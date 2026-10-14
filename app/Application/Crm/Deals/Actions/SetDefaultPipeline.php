<?php

namespace App\Application\Crm\Deals\Actions;

use App\Application\Crm\Deals\DealAuthorization;
use App\Models\CrmPipeline;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetDefaultPipeline
{
    public function __construct(private DealAuthorization $auth) {}

    public function handle(CrmPipeline $pipeline, User $actor): CrmPipeline
    {
        $this->auth->authorize($this->auth->canManagePipelines($actor, (int) $pipeline->organization_id));

        return DB::transaction(function () use ($pipeline): CrmPipeline {
            $locked = CrmPipeline::query()->whereKey($pipeline->id)->lockForUpdate()->first();

            if (! $locked instanceof CrmPipeline) {
                throw new \RuntimeException('Pipeline disappeared before default selection.');
            }

            if (! $locked->is_active) {
                throw ValidationException::withMessages([
                    'pipeline' => 'پایپ‌لاین غیرفعال نمی‌تواند پیش‌فرض باشد.',
                ]);
            }

            CrmPipeline::query()
                ->where('organization_id', $locked->organization_id)
                ->whereKeyNot($locked->id)
                ->update(['is_default' => false]);

            $locked->update(['is_default' => true]);

            return $locked->fresh() ?? $locked;
        });
    }
}
