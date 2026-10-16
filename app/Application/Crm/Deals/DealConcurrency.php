<?php

namespace App\Application\Crm\Deals;

use App\Models\CrmDeal;
use Illuminate\Validation\ValidationException;

class DealConcurrency
{
    public function assertExpectedVersion(CrmDeal $deal, mixed $expectedVersion): void
    {
        if ($expectedVersion === null || $expectedVersion === '') {
            return;
        }

        if (! is_numeric($expectedVersion) || (int) $expectedVersion !== (int) $deal->version) {
            throw ValidationException::withMessages([
                'deal' => 'این معامله هم‌زمان ویرایش شده است. صفحه را تازه کنید و دوباره تلاش کنید.',
            ]);
        }
    }

    public function assertExpectedUpdatedAt(CrmDeal $deal, mixed $expectedUpdatedAt): void
    {
        if (! is_string($expectedUpdatedAt) || $expectedUpdatedAt === '') {
            return;
        }

        if ($deal->updated_at?->toDateTimeString() !== $expectedUpdatedAt) {
            throw ValidationException::withMessages([
                'deal' => 'این معامله هم‌زمان ویرایش شده است. صفحه را تازه کنید و دوباره تلاش کنید.',
            ]);
        }
    }

    /** @param  array<string, mixed>  $attributes */
    public function bump(CrmDeal $deal, array $attributes = []): void
    {
        $deal->fill($attributes);
        $deal->version = ((int) $deal->version) + 1;
        $deal->save();
    }
}
