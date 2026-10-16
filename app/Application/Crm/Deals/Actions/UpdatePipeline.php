<?php

namespace App\Application\Crm\Deals\Actions;

use App\Application\Crm\Deals\DealAuthorization;
use App\Models\CrmPipeline;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdatePipeline
{
    public function __construct(private DealAuthorization $auth) {}

    public function handle(
        CrmPipeline $pipeline,
        User $actor,
        string $name,
        ?string $description,
        bool $isActive,
        ?bool $showWinProbability = null,
    ): CrmPipeline {
        $this->auth->authorize($this->auth->canManagePipelines($actor, (int) $pipeline->organization_id));
        $name = CrmPipeline::assertName($name);
        $description = CrmPipeline::assertDescription($description);

        return DB::transaction(function () use ($pipeline, $name, $description, $isActive, $showWinProbability): CrmPipeline {
            $locked = CrmPipeline::query()->whereKey($pipeline->id)->lockForUpdate()->first();

            if (! $locked instanceof CrmPipeline) {
                throw new \RuntimeException('Pipeline disappeared before update.');
            }

            if ($locked->is_default && ! $isActive) {
                throw ValidationException::withMessages([
                    'is_active' => 'پایپ‌لاین پیش‌فرض را نمی‌توان غیرفعال کرد. ابتدا پیش‌فرض دیگری انتخاب کنید.',
                ]);
            }

            $payload = [
                'name' => $name,
                'description' => $description,
                'is_active' => $isActive,
            ];

            if ($showWinProbability !== null) {
                $payload['show_win_probability'] = $showWinProbability;
            }

            $locked->update($payload);

            return $locked->fresh('stages') ?? $locked;
        });
    }
}
