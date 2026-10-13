<?php

namespace App\Application\Customer\Actions;

use App\Domain\Customer\CustomerLifecycleStage;
use App\Models\Customer;
use App\Models\CustomerLifecycleEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeCustomerLifecycle
{
    public function change(
        Customer $customer,
        ?string $toStage,
        ?int $actorUserId,
        string $source,
        ?string $reason = null,
        ?string $expectedStage = null,
        bool $checkExpected = false,
        bool $confirmed = false,
    ): Customer {
        $target = $this->normalize($toStage);
        $expected = $checkExpected ? $this->normalize($expectedStage) : null;

        if ($target === CustomerLifecycleStage::Customer->value && ! $confirmed) {
            throw ValidationException::withMessages([
                'lifecycle' => 'انتقال به مرحله مشتری نیاز به تأیید تبدیل واقعی دارد.',
            ]);
        }

        return DB::transaction(function () use ($customer, $target, $actorUserId, $source, $reason, $expected, $checkExpected): Customer {
            $locked = Customer::query()->whereKey($customer->id)->lockForUpdate()->first();

            if (! $locked instanceof Customer) {
                throw new \RuntimeException('Customer disappeared before lifecycle change.');
            }

            $current = $locked->lifecycle_stage?->value;

            if ($checkExpected && $current !== $expected) {
                throw ValidationException::withMessages([
                    'lifecycle' => 'مرحله این شخص هم‌زمان تغییر کرده است. صفحه را تازه کنید.',
                ]);
            }

            if ($current === $target) {
                return $locked;
            }

            $locked->update([
                'lifecycle_stage' => $target,
            ]);

            CustomerLifecycleEvent::query()->create([
                'organization_id' => $locked->organization_id,
                'customer_id' => $locked->id,
                'from_stage' => $current,
                'to_stage' => $target,
                'actor_user_id' => $actorUserId,
                'source' => $source,
                'reason' => $reason,
                'changed_at' => now(),
            ]);

            return $locked->fresh() ?? $locked;
        });
    }

    private function normalize(?string $stage): ?string
    {
        if ($stage === null || $stage === '') {
            return null;
        }

        $known = CustomerLifecycleStage::tryFrom($stage);

        if (! $known instanceof CustomerLifecycleStage) {
            throw ValidationException::withMessages([
                'lifecycle' => 'مرحله چرخه عمر معتبر نیست.',
            ]);
        }

        return $known->value;
    }
}
