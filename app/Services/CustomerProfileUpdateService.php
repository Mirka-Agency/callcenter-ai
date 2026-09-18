<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CustomerProfileUpdateService
{
    public function __construct(
        private CustomerPhoneResolver $phoneResolver,
        private CustomerCompanyResolver $companyResolver,
        private CustomerCompanyService $companyService,
    ) {}

    /**
     * @param  array{name?: ?string, company_name?: ?string, customer_company_id?: ?int, phone_number?: string, email?: ?string, job_title?: ?string}  $data
     */
    public function update(Customer $customer, array $data): Customer
    {
        $validated = Validator::make($data, [
            'name' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'customer_company_id' => ['nullable', 'integer', 'exists:customer_companies,id'],
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
                'phone_number' => 'مشتری دیگری با این شماره در شرکت ثبت شده است.',
            ]);
        }

        $phoneChanged = $customer->normalized_phone !== $normalized;
        $previousCompanyId = $customer->customer_company_id;

        [$companyId, $companyName] = $this->resolveCompanyAssignment(
            $customer->organization_id,
            $validated,
        );

        $customer->update([
            'name' => blank($validated['name'] ?? null) ? null : trim($validated['name']),
            'customer_company_id' => $companyId,
            'company_name' => $companyName,
            'phone_number' => trim($validated['phone_number']),
            'normalized_phone' => $normalized,
            'email' => blank($validated['email'] ?? null) ? null : trim($validated['email']),
            'job_title' => blank($validated['job_title'] ?? null) ? null : trim($validated['job_title']),
        ]);

        if ($phoneChanged) {
            app(CustomerIntelligenceService::class)->relinkCallsByPhone($customer->fresh());
        }

        $customer = $customer->fresh();

        foreach (array_filter([$previousCompanyId, $customer->customer_company_id]) as $id) {
            $company = \App\Models\CustomerCompany::query()->find($id);

            if ($company) {
                $this->companyService->refreshAggregates($company);
            }
        }

        return $customer;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: ?int, 1: ?string}
     */
    private function resolveCompanyAssignment(int $organizationId, array $validated): array
    {
        if (! empty($validated['customer_company_id'])) {
            $company = \App\Models\CustomerCompany::query()
                ->where('organization_id', $organizationId)
                ->find($validated['customer_company_id']);

            if (! $company) {
                throw ValidationException::withMessages([
                    'customer_company_id' => 'شرکت انتخاب‌شده معتبر نیست.',
                ]);
            }

            return [$company->id, $company->name];
        }

        $companyName = blank($validated['company_name'] ?? null)
            ? null
            : trim((string) $validated['company_name']);

        if ($companyName === null) {
            return [null, null];
        }

        $company = $this->companyResolver->findOrCreate($organizationId, $companyName);

        return [$company->id, $company->name];
    }
}
