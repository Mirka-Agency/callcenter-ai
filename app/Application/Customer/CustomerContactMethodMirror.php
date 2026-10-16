<?php

namespace App\Application\Customer;

use App\Models\Customer;
use App\Models\CustomerContactMethod;
use App\Services\CustomerPhoneResolver;
use App\Support\CustomerFieldProvenance;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Keeps customers.phone_number / email mirrored onto contact methods.
 * The legacy columns stay the primary phone and primary email.
 */
class CustomerContactMethodMirror
{
    public function __construct(private CustomerPhoneResolver $phones) {}

    public function mirrorFromCustomer(Customer $customer): void
    {
        $this->mirrorPhone($customer);
        $this->mirrorEmail($customer);

        if (blank($customer->email)) {
            CustomerContactMethod::query()
                ->where('customer_id', $customer->id)
                ->where('type', 'email')
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }
    }

    public function mirrorPhone(Customer $customer): void
    {
        $raw = trim((string) ($customer->phone_number ?: $customer->normalized_phone));
        $inspection = $this->phones->inspect($raw);

        if (! $inspection->isValid || $inspection->normalized === null) {
            return;
        }

        $normalized = $customer->normalized_phone ?: $inspection->normalized;
        $verified = CustomerFieldProvenance::isHumanLocked($customer->identity_field_sources, 'phone_number');

        $this->upsertPrimary(
            $customer,
            $inspection->reason === 'landline' ? 'phone' : 'mobile',
            $raw,
            $normalized,
            $verified ? 'verified' : 'unverified',
            $verified ? 'manual' : 'system',
            ['mobile', 'phone'],
        );
    }

    public function mirrorEmail(Customer $customer): void
    {
        $email = trim((string) $customer->email);

        if ($email === '') {
            return;
        }

        $verified = CustomerFieldProvenance::isHumanLocked($customer->identity_field_sources, 'email');

        $this->upsertPrimary(
            $customer,
            'email',
            $email,
            mb_strtolower($email),
            $verified ? 'verified' : 'unverified',
            $verified ? 'manual' : 'system',
            ['email'],
        );
    }

    private function upsertPrimary(
        Customer $customer,
        string $type,
        string $raw,
        string $normalized,
        string $verification,
        string $source,
        array $primaryTypes,
    ): void {
        $key = [
            'customer_id' => $customer->id,
            'type' => $type,
            'normalized_value' => $normalized,
        ];
        $values = [
            'organization_id' => $customer->organization_id,
            'raw_value' => $raw,
            'is_primary' => true,
            'is_active' => true,
            'verification_status' => $verification,
            'source' => $source,
        ];

        try {
            $method = CustomerContactMethod::query()->firstOrCreate($key, $values);
        } catch (UniqueConstraintViolationException) {
            $method = CustomerContactMethod::query()->where($key)->first();
        }

        if (! $method) {
            return;
        }

        if (! $method->is_active || ! $method->is_primary || $method->raw_value !== $raw) {
            $method->update([
                'raw_value' => $raw,
                'is_primary' => true,
                'is_active' => true,
                'verification_status' => $method->verification_status === 'verified' ? 'verified' : $verification,
                'source' => $method->source === 'manual' ? 'manual' : $source,
            ]);
        }

        CustomerContactMethod::query()
            ->where('customer_id', $customer->id)
            ->whereIn('type', $primaryTypes)
            ->whereKeyNot($method->id)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }
}
