<?php

namespace App\Services;

use App\Application\Customer\CustomerContactMethodMirror;
use App\Domain\Customer\CustomerLifecycleStage;
use App\Models\Customer;
use App\Models\CustomerCompany;
use App\Support\CustomerFieldProvenance;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CustomerProfileUpdateService
{
    public function __construct(
        private CustomerPhoneResolver $phoneResolver,
        private CustomerCompanyResolver $companyResolver,
        private CustomerCompanyService $companyService,
        private CustomerContactMethodMirror $contactMethods,
    ) {}

    /**
     * @param  array{name?: ?string, company_name?: ?string, customer_company_id?: ?int, phone_number?: string, email?: ?string, job_title?: ?string}  $data
     */
    public function create(int $organizationId, array $data): Customer
    {
        $validated = $this->validatePayload($data);
        $normalized = $this->normalizedUniquePhone($organizationId, $validated['phone_number']);
        [$companyId, $companyName] = $this->resolveCompanyAssignment($organizationId, $validated);

        $attributes = $this->contactAttributes($validated, $normalized, $companyId, $companyName);

        $customer = Customer::query()->create([
            'organization_id' => $organizationId,
            ...$attributes,
            'lifecycle_stage' => CustomerLifecycleStage::NewLead,
            'identity_field_sources' => CustomerFieldProvenance::lockProvided([], $attributes),
        ]);

        $this->contactMethods->mirrorFromCustomer($customer);

        if ($customer->customer_company_id) {
            $this->refreshCompanyAggregates($customer->customer_company_id);
        }

        return $customer->fresh();
    }

    /**
     * @param  array{name?: ?string, company_name?: ?string, customer_company_id?: ?int, phone_number?: string, email?: ?string, job_title?: ?string}  $data
     */
    public function update(Customer $customer, array $data): Customer
    {
        $validated = $this->validatePayload($data);
        $normalized = $this->normalizedUniquePhone(
            $customer->organization_id,
            $validated['phone_number'],
            $customer->id,
        );

        $previousCompanyId = $customer->customer_company_id;

        [$companyId, $companyName] = $this->resolveCompanyAssignment(
            $customer->organization_id,
            $validated,
        );

        $attributes = $this->contactAttributes($validated, $normalized, $companyId, $companyName);
        $phoneChanging = $customer->normalized_phone !== $normalized;
        $emailChanging = (string) $customer->email !== (string) ($attributes['email'] ?? '');

        if ($phoneChanging) {
            $this->contactMethods->mirrorPhone($customer);
        }

        if ($emailChanging && filled($customer->email)) {
            $this->contactMethods->mirrorEmail($customer);
        }

        $customer->update([
            ...$attributes,
            'identity_field_sources' => CustomerFieldProvenance::lockChanged(
                $customer->identity_field_sources ?? [],
                $customer->only(CustomerFieldProvenance::LOCKABLE),
                $attributes,
            ),
        ]);

        $this->contactMethods->mirrorFromCustomer($customer->fresh());

        $customer = $customer->fresh();

        foreach (array_filter([$previousCompanyId, $customer->customer_company_id]) as $id) {
            $this->refreshCompanyAggregates((int) $id);
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
            $company = CustomerCompany::query()
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

    /** @param  array<string, mixed>  $data */
    private function validatePayload(array $data): array
    {
        return Validator::make($data, [
            'name' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'customer_company_id' => ['nullable', 'integer', 'exists:customer_companies,id'],
            'phone_number' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
        ])->validate();
    }

    private function normalizedUniquePhone(int $organizationId, string $phoneNumber, ?int $exceptCustomerId = null): string
    {
        $inspection = $this->phoneResolver->inspect($phoneNumber);
        $normalized = $inspection->normalized;
        $keys = $this->phoneResolver->equivalentKeys($phoneNumber);

        if (! $inspection->isValid || ! $normalized || $keys === []) {
            throw ValidationException::withMessages([
                'phone_number' => 'شماره تماس معتبر نیست.',
            ]);
        }

        $duplicate = Customer::query()
            ->where('organization_id', $organizationId)
            ->whereIn('normalized_phone', $keys)
            ->when($exceptCustomerId, fn ($query) => $query->whereKeyNot($exceptCustomerId))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'phone_number' => 'مشتری دیگری با این شماره در شرکت ثبت شده است.',
            ]);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{name: ?string, customer_company_id: ?int, company_name: ?string, phone_number: string, normalized_phone: string, email: ?string, job_title: ?string}
     */
    private function contactAttributes(array $validated, string $normalized, ?int $companyId, ?string $companyName): array
    {
        return [
            'name' => blank($validated['name'] ?? null) ? null : trim($validated['name']),
            'customer_company_id' => $companyId,
            'company_name' => $companyName,
            'phone_number' => trim($validated['phone_number']),
            'normalized_phone' => $normalized,
            'email' => blank($validated['email'] ?? null) ? null : trim($validated['email']),
            'job_title' => blank($validated['job_title'] ?? null) ? null : trim($validated['job_title']),
        ];
    }

    private function refreshCompanyAggregates(int $companyId): void
    {
        $company = CustomerCompany::query()->find($companyId);

        if ($company) {
            $this->companyService->refreshAggregates($company);
        }
    }
}
