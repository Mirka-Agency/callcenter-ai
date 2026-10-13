<?php

namespace App\Application\Customer\Actions;

use App\Models\ConversationAnalysis;
use App\Models\Customer;
use App\Models\CustomerOwnerEvent;
use App\Models\OrganizationUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignCustomerOwner
{
    public function assign(
        Customer $customer,
        ?int $newOwnerOrganizationUserId,
        ?int $actorUserId,
        string $source,
        ?string $reason = null,
        ?int $expectedOwnerOrganizationUserId = null,
        bool $checkExpected = false,
        bool $onlyIfUnassigned = false,
        bool $ignoreInvalidOwner = false,
    ): Customer {
        return DB::transaction(function () use (
            $customer,
            $newOwnerOrganizationUserId,
            $actorUserId,
            $source,
            $reason,
            $expectedOwnerOrganizationUserId,
            $checkExpected,
            $onlyIfUnassigned,
            $ignoreInvalidOwner,
        ): Customer {
            $locked = Customer::query()->whereKey($customer->id)->lockForUpdate()->first();

            if (! $locked instanceof Customer) {
                throw new \RuntimeException('Customer disappeared before owner assignment.');
            }

            $current = $this->normalizeId($locked->owner_organization_user_id);

            if ($checkExpected && $current !== $expectedOwnerOrganizationUserId) {
                throw ValidationException::withMessages([
                    'owner' => 'مسئول این شخص هم‌زمان تغییر کرده است. صفحه را تازه کنید.',
                ]);
            }

            if ($onlyIfUnassigned && $current !== null) {
                return $locked;
            }

            if ($current === $newOwnerOrganizationUserId) {
                return $locked;
            }

            if ($newOwnerOrganizationUserId !== null && ! $this->isEligibleOwner($locked, $newOwnerOrganizationUserId)) {
                if ($ignoreInvalidOwner) {
                    return $locked;
                }

                throw ValidationException::withMessages([
                    'owner' => 'مسئول باید عضو فعال همین سازمان باشد.',
                ]);
            }

            $locked->update([
                'owner_organization_user_id' => $newOwnerOrganizationUserId,
            ]);

            CustomerOwnerEvent::query()->create([
                'organization_id' => $locked->organization_id,
                'customer_id' => $locked->id,
                'previous_owner_organization_user_id' => $current,
                'new_owner_organization_user_id' => $newOwnerOrganizationUserId,
                'actor_user_id' => $actorUserId,
                'source' => $source,
                'reason' => $reason,
            ]);

            return $locked->fresh(['owner']) ?? $locked;
        });
    }

    public function assignFromFirstCall(Customer $customer, ConversationAnalysis $analysis): Customer
    {
        $customer->loadMissing('organization');

        if (! $customer->organization?->assign_customer_owner_from_call) {
            return $customer;
        }

        $memberId = $analysis->call?->organization_user_id;

        if (! $memberId) {
            return $customer;
        }

        return $this->assign(
            customer: $customer,
            newOwnerOrganizationUserId: (int) $memberId,
            actorUserId: null,
            source: 'system',
            reason: 'first_call',
            onlyIfUnassigned: true,
            ignoreInvalidOwner: true,
        );
    }

    private function isEligibleOwner(Customer $customer, int $ownerOrganizationUserId): bool
    {
        $member = OrganizationUser::query()->whereKey($ownerOrganizationUserId)->first();

        return $member instanceof OrganizationUser
            && (int) $member->organization_id === (int) $customer->organization_id
            && (bool) $member->is_active;
    }

    private function normalizeId(mixed $id): ?int
    {
        return $id === null || $id === '' ? null : (int) $id;
    }
}
