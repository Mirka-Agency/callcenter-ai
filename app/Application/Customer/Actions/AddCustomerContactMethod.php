<?php

namespace App\Application\Customer\Actions;

use App\Application\Customer\CustomerContactMethodMirror;
use App\Models\Customer;
use App\Models\CustomerContactMethod;
use App\Services\CustomerPhoneResolver;
use App\Support\CustomerTenantGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddCustomerContactMethod
{
    public function __construct(
        private CustomerPhoneResolver $phones,
        private CustomerContactMethodMirror $mirror,
    ) {}

    public function handle(Customer $customer, string $value, ?string $label = null, bool $primary = false): CustomerContactMethod
    {
        $value = trim($value);

        if (str_contains($value, '@')) {
            return $this->addEmail($customer, $value, $label, $primary);
        }

        return $this->addPhone($customer, $value, $label, $primary);
    }

    private function addPhone(Customer $customer, string $value, ?string $label, bool $primary): CustomerContactMethod
    {
        $inspection = $this->phones->inspect($value);

        if (! $inspection->isValid || $inspection->normalized === null) {
            throw ValidationException::withMessages([
                'value' => 'شماره تماس معتبر نیست.',
            ]);
        }

        $type = $inspection->reason === 'landline' ? 'phone' : 'mobile';
        $this->assertNotDuplicatePhone($customer, $inspection->normalized);

        if ($primary) {
            $this->assertLegacyPhoneAvailable($customer, $inspection->normalized);
        }

        return DB::transaction(function () use ($customer, $value, $label, $primary, $type, $inspection) {
            $method = CustomerContactMethod::query()->updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'type' => $type,
                    'normalized_value' => $inspection->normalized,
                ],
                [
                    'organization_id' => $customer->organization_id,
                    'raw_value' => $value,
                    'label' => blank($label) ? null : trim($label),
                    'is_primary' => $primary,
                    'is_active' => true,
                    'verification_status' => 'verified',
                    'source' => 'manual',
                ],
            );

            if ($primary) {
                $customer->update([
                    'phone_number' => $value,
                    'normalized_phone' => $inspection->normalized,
                ]);
                $this->mirror->mirrorPhone($customer->fresh());
            }

            return $method->fresh();
        });
    }

    private function addEmail(Customer $customer, string $value, ?string $label, bool $primary): CustomerContactMethod
    {
        $normalized = mb_strtolower($value);

        if (! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'value' => 'ایمیل معتبر نیست.',
            ]);
        }

        $exists = CustomerContactMethod::query()
            ->where('customer_id', $customer->id)
            ->where('type', 'email')
            ->where('normalized_value', $normalized)
            ->where('is_active', true)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'value' => 'این ایمیل برای همین شخص ثبت شده است.',
            ]);
        }

        return DB::transaction(function () use ($customer, $value, $label, $primary, $normalized) {
            $method = CustomerContactMethod::query()->updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'type' => 'email',
                    'normalized_value' => $normalized,
                ],
                [
                    'organization_id' => $customer->organization_id,
                    'raw_value' => $value,
                    'label' => blank($label) ? null : trim($label),
                    'is_primary' => $primary,
                    'is_active' => true,
                    'verification_status' => 'verified',
                    'source' => 'manual',
                ],
            );

            if ($primary) {
                $customer->update(['email' => $normalized]);
                $this->mirror->mirrorEmail($customer->fresh());
            }

            return $method->fresh();
        });
    }

    private function assertNotDuplicatePhone(Customer $customer, string $normalized): void
    {
        $keys = $this->phones->equivalentKeys($normalized);
        $duplicate = CustomerContactMethod::query()
            ->where('customer_id', $customer->id)
            ->whereIn('type', ['mobile', 'phone'])
            ->where('is_active', true)
            ->whereIn('normalized_value', $keys)
            ->exists();

        if ($duplicate || ($customer->normalized_phone && in_array($customer->normalized_phone, $keys, true))) {
            throw ValidationException::withMessages([
                'value' => 'این شماره برای همین شخص ثبت شده است.',
            ]);
        }
    }

    private function assertLegacyPhoneAvailable(Customer $customer, string $normalized): void
    {
        $keys = $this->phones->equivalentKeys($normalized);
        $taken = Customer::query()
            ->where('organization_id', $customer->organization_id)
            ->whereKeyNot($customer->id)
            ->whereIn('normalized_phone', $keys)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'value' => 'این شماره، شماره اصلی شخص دیگری در همین سازمان است و نمی‌تواند اصلی شود.',
            ]);
        }

        CustomerTenantGuard::tenantPhoneKey($customer->organization_id, $normalized);
    }
}
