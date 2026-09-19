<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerCompany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CustomerCompanyUpdateService
{
    /**
     * @param  array{name: string, industry?: ?string, website?: ?string, phone?: ?string, email?: ?string, address?: ?string, notes?: ?string}  $data
     */
    public function create(int $organizationId, array $data): CustomerCompany
    {
        $validated = $this->validate($data);

        $normalized = CustomerCompany::normalizeName($validated['name']);

        $duplicate = CustomerCompany::query()
            ->where('organization_id', $organizationId)
            ->where('normalized_name', $normalized)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'name' => 'شرکتی با این نام قبلاً ثبت شده است.',
            ]);
        }

        return CustomerCompany::query()->create([
            'organization_id' => $organizationId,
            'name' => trim($validated['name']),
            'normalized_name' => $normalized,
            'industry' => $this->nullableString($validated['industry'] ?? null),
            'website' => $this->nullableString($validated['website'] ?? null),
            'phone' => $this->nullableString($validated['phone'] ?? null),
            'email' => $this->nullableString($validated['email'] ?? null),
            'address' => $this->nullableString($validated['address'] ?? null),
            'notes' => $this->nullableString($validated['notes'] ?? null),
        ]);
    }

    /**
     * @param  array{name?: string, industry?: ?string, website?: ?string, phone?: ?string, email?: ?string, address?: ?string, notes?: ?string}  $data
     */
    public function update(CustomerCompany $company, array $data): CustomerCompany
    {
        $validated = $this->validate($data, updating: true);

        $updates = [];

        if (array_key_exists('name', $validated)) {
            $normalized = CustomerCompany::normalizeName($validated['name']);

            $duplicate = CustomerCompany::query()
                ->where('organization_id', $company->organization_id)
                ->where('normalized_name', $normalized)
                ->whereKeyNot($company->id)
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'name' => 'شرکتی با این نام قبلاً ثبت شده است.',
                ]);
            }

            $updates['name'] = trim($validated['name']);
            $updates['normalized_name'] = $normalized;
        }

        foreach (['industry', 'website', 'phone', 'email', 'address', 'notes'] as $field) {
            if (array_key_exists($field, $validated)) {
                $updates[$field] = $this->nullableString($validated[$field]);
            }
        }

        if ($updates !== []) {
            $company->update($updates);

            if (isset($updates['name'])) {
                Customer::query()
                    ->where('customer_company_id', $company->id)
                    ->update(['company_name' => $company->fresh()->name]);
            }
        }

        return $company->fresh();
    }

    /** @param  array<string, mixed>  $data */
    private function validate(array $data, bool $updating = false): array
    {
        $rules = [
            'name' => [$updating ? 'sometimes' : 'required', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:120'],
            'website' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        return Validator::make($data, $rules)->validate();
    }

    private function nullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
