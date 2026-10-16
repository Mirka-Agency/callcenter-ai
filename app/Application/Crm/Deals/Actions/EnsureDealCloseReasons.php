<?php

namespace App\Application\Crm\Deals\Actions;

use App\Domain\Crm\Deals\DefaultDealCloseReasons;
use App\Models\CrmDealCloseReason;
use Illuminate\Support\Collection;

class EnsureDealCloseReasons
{
    /** @return Collection<int, CrmDealCloseReason> */
    public function handle(int $organizationId): Collection
    {
        foreach (DefaultDealCloseReasons::catalog() as $row) {
            CrmDealCloseReason::query()->firstOrCreate(
                [
                    'organization_id' => $organizationId,
                    'kind' => $row['kind'],
                    'code' => $row['code'],
                ],
                [
                    'label' => $row['label'],
                    'position' => $row['position'],
                    'is_active' => true,
                    'requires_note' => $row['requires_note'],
                ],
            );
        }

        return CrmDealCloseReason::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->orderBy('kind')
            ->orderBy('position')
            ->get();
    }
}
