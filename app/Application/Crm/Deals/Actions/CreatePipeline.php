<?php

namespace App\Application\Crm\Deals\Actions;

use App\Application\Crm\Deals\DealAuthorization;
use App\Domain\Crm\Deals\PipelineStagePalette;
use App\Models\CrmPipeline;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreatePipeline
{
    public function __construct(private DealAuthorization $auth) {}

    public function handle(
        int $organizationId,
        User $actor,
        string $name,
        ?string $description = null,
        bool $withDefaultStages = true,
        ?string $kind = null,
    ): CrmPipeline {
        $this->auth->authorize($this->auth->canManagePipelines($actor, $organizationId));
        $name = CrmPipeline::assertName($name);
        $description = CrmPipeline::assertDescription($description);
        $kind = PipelineStagePalette::normalizeKind($kind);

        return DB::transaction(function () use ($organizationId, $name, $description, $withDefaultStages, $kind): CrmPipeline {
            $hasAny = CrmPipeline::query()
                ->where('organization_id', $organizationId)
                ->lockForUpdate()
                ->exists();

            $pipeline = CrmPipeline::query()->create([
                'organization_id' => $organizationId,
                'name' => $name,
                'description' => $description,
                'is_default' => ! $hasAny,
                'is_active' => true,
            ]);

            if ($withDefaultStages) {
                $pipeline->seedDefaultStages($kind);
            }

            return $pipeline->load('stages');
        });
    }
}
