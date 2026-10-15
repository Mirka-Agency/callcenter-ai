<?php

namespace App\Application\Crm\Tasks;

use App\Models\CrmDeal;
use App\Models\Customer;
use App\Models\CustomerCompany;
use App\Models\OrganizationUser;
use Illuminate\Validation\ValidationException;

class TaskTenant
{
    public function customer(int $organizationId, int $customerId): Customer
    {
        $customer = Customer::query()
            ->where('organization_id', $organizationId)
            ->whereKey($customerId)
            ->first();

        if (! $customer instanceof Customer) {
            throw ValidationException::withMessages([
                'customer_id' => 'شخص انتخاب‌شده به این سازمان تعلق ندارد.',
            ]);
        }

        return $customer;
    }

    public function company(int $organizationId, int $companyId): CustomerCompany
    {
        $company = CustomerCompany::query()
            ->where('organization_id', $organizationId)
            ->whereKey($companyId)
            ->first();

        if (! $company instanceof CustomerCompany) {
            throw ValidationException::withMessages([
                'customer_company_id' => 'شرکت انتخاب‌شده به این سازمان تعلق ندارد.',
            ]);
        }

        return $company;
    }

    public function deal(int $organizationId, int $dealId): CrmDeal
    {
        $deal = CrmDeal::query()
            ->where('organization_id', $organizationId)
            ->whereKey($dealId)
            ->first();

        if (! $deal instanceof CrmDeal) {
            throw ValidationException::withMessages([
                'crm_deal_id' => 'معامله انتخاب‌شده به این سازمان تعلق ندارد.',
            ]);
        }

        return $deal;
    }

    public function assignee(int $organizationId, int $membershipId): OrganizationUser
    {
        $member = OrganizationUser::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->whereKey($membershipId)
            ->first();

        if (! $member instanceof OrganizationUser) {
            throw ValidationException::withMessages([
                'assignee' => 'مسئول باید عضو فعال همین سازمان باشد.',
            ]);
        }

        return $member;
    }

    public function assertDealCustomerCompatible(?CrmDeal $deal, ?Customer $customer): void
    {
        if (! $deal || ! $customer) {
            return;
        }

        $linked = $deal->contacts()->where('customer_id', $customer->id)->exists();

        if (! $linked) {
            throw ValidationException::withMessages([
                'customer_id' => 'این شخص به معامله انتخاب‌شده متصل نیست.',
            ]);
        }
    }
}
