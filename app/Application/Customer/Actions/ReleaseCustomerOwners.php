<?php

namespace App\Application\Customer\Actions;

use App\Models\Customer;
use App\Models\OrganizationUser;
use Illuminate\Support\Facades\DB;

class ReleaseCustomerOwners
{
    public function __construct(private AssignCustomerOwner $owners) {}

    public function handle(OrganizationUser $member, string $reason): void
    {
        DB::transaction(function () use ($member, $reason): void {
            $customerIds = Customer::query()
                ->where('organization_id', $member->organization_id)
                ->where('owner_organization_user_id', $member->id)
                ->orderBy('id')
                ->pluck('id');

            foreach ($customerIds as $customerId) {
                $customer = Customer::query()->find($customerId);

                if (! $customer instanceof Customer) {
                    continue;
                }

                $this->owners->assign(
                    customer: $customer,
                    newOwnerOrganizationUserId: null,
                    actorUserId: auth()->id(),
                    source: 'membership_ended',
                    reason: $reason,
                );
            }
        });
    }
}
