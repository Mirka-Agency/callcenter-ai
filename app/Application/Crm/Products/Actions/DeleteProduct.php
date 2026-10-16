<?php

namespace App\Application\Crm\Products\Actions;

use App\Application\Crm\Products\ProductAuthorization;
use App\Models\CrmProduct;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteProduct
{
    public function __construct(private ProductAuthorization $auth) {}

    public function handle(CrmProduct $product, User $actor): void
    {
        $this->auth->assertInternalCrm((int) $product->organization_id);
        $this->auth->authorize($this->auth->canDelete($actor, $product));

        DB::transaction(function () use ($product): void {
            $product->delete();
        });
    }
}
