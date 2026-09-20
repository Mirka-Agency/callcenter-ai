<?php

namespace App\Services;

use App\Models\CustomerCompany;
use App\Models\Organization;
use App\Support\CompanyName;
use Illuminate\Database\UniqueConstraintViolationException;

class CustomerCompanyResolver
{
    public function findOrCreate(int $organizationId, string $name, bool $excludeOwnOrganization = false): ?CustomerCompany
    {
        $display = CompanyName::display($name);
        $key = CompanyName::key($display);

        if ($key === '') {
            throw new \InvalidArgumentException('Company name cannot be empty.');
        }

        if ($excludeOwnOrganization) {
            $organizationTitle = Organization::query()->whereKey($organizationId)->value('title');

            if (is_string($organizationTitle) && CompanyName::isOwnOrganization($display, $organizationTitle)) {
                return null;
            }
        }

        $company = $this->findExisting($organizationId, $display, $key);

        if ($company) {
            return $this->correctExisting($company, $display, $key);
        }

        try {
            return CustomerCompany::query()->create([
                'organization_id' => $organizationId,
                'name' => $display,
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->findExisting($organizationId, $display, $key)?->fresh();
        }
    }

    private function findExisting(int $organizationId, string $display, string $key): ?CustomerCompany
    {
        $keys = CompanyName::identityKeys($display);
        $names = CompanyName::identityNames($display);

        return CustomerCompany::query()
            ->where('organization_id', $organizationId)
            ->where(function ($query) use ($keys, $names, $key) {
                $query->where('normalized_name', $key)
                    ->orWhereIn('normalized_name', $keys)
                    ->orWhereIn('name', $names);
            })
            ->orderBy('id')
            ->first();
    }

    private function correctExisting(CustomerCompany $company, string $display, string $key): CustomerCompany
    {
        $preferred = CompanyName::preferDisplay($company->name, $display);

        if ($preferred === $company->name && $company->normalized_name === $key) {
            return $company;
        }

        try {
            $company->update(['name' => $preferred]);
        } catch (UniqueConstraintViolationException) {
            $existing = CustomerCompany::query()
                ->where('organization_id', $company->organization_id)
                ->where('normalized_name', $key)
                ->whereKeyNot($company->id)
                ->first();

            if ($existing) {
                return $existing->fresh();
            }
        }

        return $company->fresh() ?? $company;
    }
}
