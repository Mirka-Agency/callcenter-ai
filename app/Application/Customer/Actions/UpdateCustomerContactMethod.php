<?php

namespace App\Application\Customer\Actions;

use App\Application\Customer\CustomerContactMethodMirror;
use App\Models\Customer;
use App\Models\CustomerContactMethod;
use App\Services\CustomerPhoneResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateCustomerContactMethod
{
    public function __construct(
        private CustomerPhoneResolver $phones,
        private CustomerContactMethodMirror $mirror,
    ) {}

    public function makePrimary(CustomerContactMethod $method): CustomerContactMethod
    {
        $customer = $method->customer;

        if (! $customer || ! $method->is_active) {
            throw ValidationException::withMessages([
                'method' => 'این روش تماس فعال نیست.',
            ]);
        }

        if ($method->isPhone()) {
            $keys = $this->phones->equivalentKeys($method->normalized_value);
            $taken = Customer::query()
                ->where('organization_id', $customer->organization_id)
                ->whereKeyNot($customer->id)
                ->whereIn('normalized_phone', $keys)
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages([
                    'method' => 'این شماره، شماره اصلی شخص دیگری است. برای اصلی کردن باید در صف بررسی بماند.',
                ]);
            }
        }

        return DB::transaction(function () use ($method, $customer) {
            CustomerContactMethod::query()
                ->where('customer_id', $customer->id)
                ->whereIn('type', $method->isPhone() ? ['mobile', 'phone'] : [$method->type])
                ->whereKeyNot($method->id)
                ->update(['is_primary' => false]);

            $method->update([
                'is_primary' => true,
                'verification_status' => 'verified',
            ]);

            if ($method->isPhone()) {
                $customer->update([
                    'phone_number' => $method->raw_value,
                    'normalized_phone' => $method->normalized_value,
                ]);
            }

            if ($method->type === 'email') {
                $customer->update(['email' => $method->normalized_value]);
            }

            $this->mirror->mirrorFromCustomer($customer->fresh());

            return $method->fresh();
        });
    }

    public function deactivate(CustomerContactMethod $method): CustomerContactMethod
    {
        if ($method->is_primary && $method->isPhone()) {
            throw ValidationException::withMessages([
                'method' => 'شماره اصلی را نمی‌توان حذف کرد. اول شماره دیگری را اصلی کنید.',
            ]);
        }

        if ($method->is_primary && $method->type === 'email') {
            $method->customer?->update(['email' => null]);
        }

        $method->update([
            'is_active' => false,
            'is_primary' => false,
        ]);

        return $method->fresh();
    }
}
