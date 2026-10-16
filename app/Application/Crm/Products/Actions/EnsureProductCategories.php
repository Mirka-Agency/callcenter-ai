<?php

namespace App\Application\Crm\Products\Actions;

use App\Models\CrmProductCategory;
use Illuminate\Support\Collection;

class EnsureProductCategories
{
    /** @return Collection<int, CrmProductCategory> */
    public function handle(int $organizationId): Collection
    {
        return CrmProductCategory::query()
            ->where('organization_id', $organizationId)
            ->orderBy('position')
            ->orderBy('name')
            ->get();
    }
}
