<?php

namespace App\Application\Crm\Deals\Actions;

use App\Models\CrmPipeline;
use Illuminate\Support\Facades\DB;

class EnsureDefaultPipeline
{
    public function handle(int $organizationId): CrmPipeline
    {
        return DB::transaction(function () use ($organizationId): CrmPipeline {
            $existing = CrmPipeline::query()
                ->where('organization_id', $organizationId)
                ->lockForUpdate()
                ->get();

            if ($existing->isNotEmpty()) {
                $pipeline = $existing->firstWhere('is_default', true) ?? $existing->first();

                return $pipeline->load('stages');
            }

            $pipeline = CrmPipeline::query()->create([
                'organization_id' => $organizationId,
                'name' => 'فروش',
                'description' => 'پایپ‌لاین پیش‌فرض فروش',
                'is_default' => true,
                'is_active' => true,
            ]);
            $pipeline->seedDefaultStages();

            return $pipeline->load('stages');
        });
    }
}
