<?php

namespace App\Application\Crm\Products;

use App\Domain\Crm\Products\ProductType;
use App\Models\CrmProduct;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ProductQuery
{
    public function __construct(private ProductAuthorization $auth) {}

    /** @param  array{type?: string, search?: string, status?: string}  $filters */
    public function paginate(int $organizationId, User $actor, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $this->auth->authorize($this->auth->canView($actor, $organizationId));

        return $this->base($organizationId, $filters)
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @param  array{type?: string, search?: string, status?: string}  $filters */
    public function counts(int $organizationId, User $actor, array $filters = []): array
    {
        $this->auth->authorize($this->auth->canView($actor, $organizationId));

        $base = $this->base($organizationId, [
            'search' => $filters['search'] ?? '',
            'status' => $filters['status'] ?? 'all',
        ]);

        return [
            'all' => (clone $base)->count(),
            'product' => (clone $base)->where('type', ProductType::Product->value)->count(),
            'service' => (clone $base)->where('type', ProductType::Service->value)->count(),
        ];
    }

    public function findVisible(int $organizationId, User $actor, int $productId): CrmProduct
    {
        $this->auth->authorize($this->auth->canView($actor, $organizationId));

        $product = CrmProduct::query()
            ->where('organization_id', $organizationId)
            ->find($productId);

        if (! $product instanceof CrmProduct) {
            abort(404);
        }

        return $product;
    }

    /** @param  array{type?: string, search?: string, status?: string}  $filters */
    private function base(int $organizationId, array $filters): Builder
    {
        $query = CrmProduct::query()->where('organization_id', $organizationId);

        $type = (string) ($filters['type'] ?? 'all');
        if ($type !== 'all' && ProductType::tryFrom($type)) {
            $query->where('type', $type);
        }

        $status = (string) ($filters['status'] ?? 'all');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $term = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        return $query;
    }
}
