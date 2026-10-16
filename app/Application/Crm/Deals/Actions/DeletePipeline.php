<?php

namespace App\Application\Crm\Deals\Actions;

use App\Application\Crm\Deals\DealAuthorization;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeletePipeline
{
    public function __construct(private DealAuthorization $auth) {}

    public function handle(CrmPipeline $pipeline, User $actor): void
    {
        $this->auth->authorize($this->auth->canManagePipelines($actor, (int) $pipeline->organization_id));

        DB::transaction(function () use ($pipeline): void {
            $locked = CrmPipeline::query()->whereKey($pipeline->id)->lockForUpdate()->first();

            if (! $locked instanceof CrmPipeline) {
                return;
            }

            $hasDeals = CrmDeal::withTrashed()
                ->where('crm_pipeline_id', $locked->id)
                ->exists();

            if ($hasDeals) {
                throw ValidationException::withMessages([
                    'pipeline' => 'پایپ‌لاین دارای معامله است. ابتدا معاملات را منتقل یا بایگانی و از این پایپ‌لاین خارج کنید.',
                ]);
            }

            $wasDefault = $locked->is_default;
            $organizationId = (int) $locked->organization_id;
            $locked->stages()->delete();
            $locked->delete();

            if ($wasDefault) {
                $replacement = CrmPipeline::query()
                    ->where('organization_id', $organizationId)
                    ->orderByDesc('is_active')
                    ->orderBy('id')
                    ->first();
                $replacement?->update(['is_default' => true]);
            }
        });
    }
}
