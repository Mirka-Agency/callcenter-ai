<?php

namespace App\Application\Customer\Actions;

use App\Models\Customer;
use App\Models\CustomerCompany;
use App\Models\CustomerIdentityReview;
use App\Support\CompanyName;
use App\Support\CustomerFieldProvenance;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResolveIdentityReview
{
    public function accept(CustomerIdentityReview $review, int $organizationId, ?int $userId): CustomerIdentityReview
    {
        $this->assertTenant($review, $organizationId);

        if ($review->status !== 'pending') {
            return $review;
        }

        if (! in_array($review->kind, ['field_suggestion', 'company_candidate', 'phone_candidate'], true)) {
            throw ValidationException::withMessages([
                'review' => 'این مورد فقط با بستن از صف خارج می‌شود و داده‌ای را تغییر نمی‌دهد.',
            ]);
        }

        return DB::transaction(function () use ($review, $userId) {
            $customer = $review->customer;

            if (! $customer || $customer->organization_id !== $review->organization_id) {
                throw ValidationException::withMessages([
                    'review' => 'شخص مرتبط با این پیشنهاد در همین سازمان پیدا نشد.',
                ]);
            }

            if ($review->kind === 'company_candidate') {
                $this->assignCompany($customer, (string) $review->suggested_value);
            } elseif ($review->kind === 'phone_candidate') {
                app(AddCustomerContactMethod::class)->handle($customer, (string) $review->suggested_value);
            } else {
                $this->assignField($customer, (string) $review->field, (string) $review->suggested_value);
            }

            $review->update([
                'status' => 'accepted',
                'resolved_by' => $userId,
                'resolved_at' => now(),
            ]);

            return $review->fresh();
        });
    }

    public function dismiss(CustomerIdentityReview $review, int $organizationId, ?int $userId): CustomerIdentityReview
    {
        $this->assertTenant($review, $organizationId);

        if ($review->status !== 'pending') {
            return $review;
        }

        $review->update([
            'status' => 'rejected',
            'resolved_by' => $userId,
            'resolved_at' => now(),
        ]);

        return $review->fresh();
    }

    private function assertTenant(CustomerIdentityReview $review, int $organizationId): void
    {
        if ($review->organization_id !== $organizationId) {
            throw ValidationException::withMessages([
                'review' => 'این مورد به سازمان دیگری تعلق دارد.',
            ]);
        }
    }

    private function assignField(Customer $customer, string $field, string $value): void
    {
        if (! in_array($field, ['name', 'email', 'job_title'], true)) {
            throw ValidationException::withMessages([
                'review' => 'این فیلد از مسیر بررسی قابل ثبت نیست.',
            ]);
        }

        if (CustomerFieldProvenance::isHumanLocked($customer->identity_field_sources, $field)) {
            throw ValidationException::withMessages([
                'review' => 'این فیلد قبلاً توسط کاربر تأیید شده است.',
            ]);
        }

        $sources = CustomerFieldProvenance::lockChanged(
            $customer->identity_field_sources ?? [],
            [$field => $customer->{$field}],
            [$field => $value],
        );

        $customer->update([
            $field => $value,
            'identity_field_sources' => $sources,
        ]);
    }

    private function assignCompany(Customer $customer, string $name): void
    {
        $display = CompanyName::display($name);

        $customer->loadMissing('organization');

        if ($display === '' || CompanyName::isOwnOrganization($display, (string) $customer->organization?->title)) {
            throw ValidationException::withMessages([
                'review' => 'نام سازمان خودتان به‌عنوان شرکت مشتری ثبت نمی‌شود.',
            ]);
        }

        if (CustomerFieldProvenance::companyIsHumanLocked($customer->identity_field_sources)) {
            throw ValidationException::withMessages([
                'review' => 'شرکت این شخص قبلاً توسط کاربر تأیید شده است.',
            ]);
        }

        $key = CompanyName::key($display);
        $company = CustomerCompany::query()->firstOrCreate(
            [
                'organization_id' => $customer->organization_id,
                'normalized_name' => $key,
            ],
            ['name' => $display],
        );

        $sources = CustomerFieldProvenance::lockChanged(
            $customer->identity_field_sources ?? [],
            [
                'company_name' => $customer->company_name,
                'customer_company_id' => $customer->customer_company_id,
            ],
            [
                'company_name' => $company->name,
                'customer_company_id' => $company->id,
            ],
        );

        $customer->update([
            'customer_company_id' => $company->id,
            'company_name' => $company->name,
            'identity_field_sources' => $sources,
        ]);
    }
}
