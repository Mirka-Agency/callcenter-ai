<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CustomerProfileUpdateService
{
    public function __construct(private CustomerPhoneResolver $phoneResolver) {}

    /**
     * @param  array{name?: ?string, company_name?: ?string, phone_number?: string, email?: ?string, job_title?: ?string}  $data
     */
    public function update(Customer $customer, array $data): Customer
    {
        $validated = Validator::make($data, [
            'name' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $normalized = $this->phoneResolver->normalize($validated['phone_number']);

        if (! $normalized) {
            throw ValidationException::withMessages([
                'phone_number' => 'شماره تماس معتبر نیست.',
            ]);
        }

        $duplicate = Customer::query()
            ->where('organization_id', $customer->organization_id)
            ->where('normalized_phone', $normalized)
            ->whereKeyNot($customer->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'phone_number' => 'مشتری دیگری با این شماره در سازمان ثبت شده است.',
            ]);
        }

        $phoneChanged = $customer->normalized_phone !== $normalized;

        $customer->update([
            'name' => blank($validated['name'] ?? null) ? null : trim($validated['name']),
            'company_name' => blank($validated['company_name'] ?? null) ? null : trim($validated['company_name']),
            'phone_number' => trim($validated['phone_number']),
            'normalized_phone' => $normalized,
            'email' => blank($validated['email'] ?? null) ? null : trim($validated['email']),
            'job_title' => blank($validated['job_title'] ?? null) ? null : trim($validated['job_title']),
        ]);

        if ($phoneChanged) {
            app(CustomerIntelligenceService::class)->relinkCallsByPhone($customer->fresh());
        }

        return $customer->fresh();
    }
}
