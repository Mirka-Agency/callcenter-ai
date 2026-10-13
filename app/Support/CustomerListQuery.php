<?php

namespace App\Support;

use App\Domain\Customer\CustomerLifecycleStage;
use App\Models\Customer;
use App\Models\CustomerCompany;
use App\Models\Organization;
use App\Models\OrganizationUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CustomerListQuery
{
    /**
     * @return Builder<Customer>
     */
    public static function contacts(
        int $organizationId,
        string $search = '',
        string $sort = 'last_contact',
        string $owner = 'all',
        ?int $viewerMembershipId = null,
        ?int $specificOwnerId = null,
        string $stage = 'all',
    ): Builder {
        $owner = in_array($owner, ['all', 'mine', 'unassigned', 'specific'], true) ? $owner : 'all';
        $stage = self::normalizeStageFilter($stage);

        $query = Customer::query()
            ->forOrganization($organizationId)
            ->with(['company', 'owner'])
            ->when($owner === 'unassigned', fn (Builder $query) => $query->whereNull('owner_organization_user_id'))
            ->when($owner === 'mine', function (Builder $query) use ($viewerMembershipId): void {
                if ($viewerMembershipId === null) {
                    $query->whereRaw('0 = 1');

                    return;
                }

                $query->where('owner_organization_user_id', $viewerMembershipId);
            })
            ->when($owner === 'specific', function (Builder $query) use ($specificOwnerId): void {
                if ($specificOwnerId === null) {
                    $query->whereRaw('0 = 1');

                    return;
                }

                $query->where('owner_organization_user_id', $specificOwnerId);
            })
            ->when($stage === 'unknown', fn (Builder $query) => $query->whereNull('lifecycle_stage'))
            ->when(
                CustomerLifecycleStage::tryFrom($stage) instanceof CustomerLifecycleStage,
                fn (Builder $query) => $query->where('lifecycle_stage', $stage),
            )
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = '%'.$search.'%';
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('name', 'like', $term)
                        ->orWhere('company_name', 'like', $term)
                        ->orWhere('phone_number', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhereHas('contactMethods', function (Builder $methods) use ($term): void {
                            $methods->where('is_active', true)
                                ->where(function (Builder $inner) use ($term): void {
                                    $inner->where('raw_value', 'like', $term)
                                        ->orWhere('normalized_value', 'like', $term);
                                });
                        })
                        ->orWhereHas('company', fn (Builder $company) => $company->where('name', 'like', $term));
                });
            });

        CustomerListSort::apply($query, $sort, 'contact', $organizationId);

        return $query;
    }

    /**
     * Counts follow the owner and search filters and ignore the selected stage.
     *
     * @return array<string, int>
     */
    public static function lifecycleCounts(
        int $organizationId,
        string $search = '',
        string $owner = 'all',
        ?int $viewerMembershipId = null,
        ?int $specificOwnerId = null,
    ): array {
        $rows = self::contacts(
            $organizationId,
            $search,
            'last_contact',
            $owner,
            $viewerMembershipId,
            $specificOwnerId,
        )
            ->setEagerLoads([])
            ->reorder()
            ->selectRaw('lifecycle_stage, COUNT(*) as aggregate')
            ->groupBy('lifecycle_stage')
            ->get();

        $counts = [
            'all' => 0,
            'unknown' => 0,
        ];

        foreach (CustomerLifecycleStage::cases() as $stage) {
            $counts[$stage->value] = 0;
        }

        foreach ($rows as $row) {
            $key = $row->lifecycle_stage instanceof CustomerLifecycleStage
                ? $row->lifecycle_stage->value
                : ($row->getAttribute('lifecycle_stage') ?: 'unknown');
            $aggregate = (int) $row->aggregate;
            $counts[$key] = ($counts[$key] ?? 0) + $aggregate;
            $counts['all'] += $aggregate;
        }

        return $counts;
    }

    /** @return Collection<int, OrganizationUser> */
    public static function ownerChoices(int $organizationId): Collection
    {
        return OrganizationUser::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    /**
     * @return Builder<CustomerCompany>
     */
    public static function companies(
        int $organizationId,
        string $search = '',
        string $sort = 'last_contact',
        bool $withContactPreview = false,
    ): Builder {
        $query = CustomerCompany::query()
            ->forOrganization($organizationId)
            ->excludingOwnOrganization(Organization::query()->find($organizationId))
            ->when(
                $withContactPreview,
                fn (Builder $query) => $query->with([
                    'contacts' => fn ($contacts) => $contacts->orderByDesc('last_contact_at')->limit(3),
                ]),
            )
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = '%'.$search.'%';
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('name', 'like', $term)
                        ->orWhere('industry', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            });

        CustomerListSort::apply($query, $sort, 'company', $organizationId);

        return $query;
    }

    private static function normalizeStageFilter(string $stage): string
    {
        if ($stage === 'all' || $stage === 'unknown') {
            return $stage;
        }

        return CustomerLifecycleStage::tryFrom($stage)?->value ?? 'all';
    }
}
