<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\CustomerCompany;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;

class CustomerListQuery
{
    /**
     * @return Builder<Customer>
     */
    public static function contacts(int $organizationId, string $search = '', string $sort = 'last_contact'): Builder
    {
        $query = Customer::query()
            ->forOrganization($organizationId)
            ->with('company')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = '%'.$search.'%';
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('name', 'like', $term)
                        ->orWhere('company_name', 'like', $term)
                        ->orWhere('phone_number', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhereHas('company', fn (Builder $company) => $company->where('name', 'like', $term));
                });
            });

        CustomerListSort::apply($query, $sort, 'contact', $organizationId);

        return $query;
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
}
