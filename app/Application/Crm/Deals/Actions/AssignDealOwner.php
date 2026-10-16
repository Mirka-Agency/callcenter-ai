<?php

namespace App\Application\Crm\Deals\Actions;

use App\Application\Crm\Deals\DealAudit;
use App\Application\Crm\Deals\DealAuthorization;
use App\Application\Crm\Deals\DealConcurrency;
use App\Application\Crm\Deals\DealState;
use App\Application\Crm\Deals\DealTenant;
use App\Domain\Crm\Deals\DealActivityType;
use App\Domain\Crm\Deals\Events\DealOwnerChanged;
use App\Models\CrmDeal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignDealOwner
{
    public function __construct(
        private DealAuthorization $auth,
        private DealTenant $tenant,
        private DealAudit $audit,
        private DealConcurrency $concurrency,
    ) {}

    public function handle(
        CrmDeal $deal,
        User $actor,
        ?int $ownerId,
        ?int $expectedOwnerId = null,
        bool $checkExpected = false,
        mixed $expectedVersion = null,
    ): CrmDeal {
        $this->auth->authorize($this->auth->canAssignOwner($actor, $deal));

        return DB::transaction(function () use ($deal, $actor, $ownerId, $expectedOwnerId, $checkExpected, $expectedVersion): CrmDeal {
            $locked = CrmDeal::query()->whereKey($deal->id)->lockForUpdate()->first();

            if (! $locked instanceof CrmDeal) {
                throw new \RuntimeException('Deal disappeared before owner assignment.');
            }

            DealState::assertOpen($locked);
            $this->concurrency->assertExpectedVersion($locked, $expectedVersion);
            $current = $locked->owner_organization_user_id ? (int) $locked->owner_organization_user_id : null;

            if ($checkExpected && $current !== $expectedOwnerId) {
                throw ValidationException::withMessages([
                    'owner' => 'مسئول معامله هم‌زمان تغییر کرده است. صفحه را تازه کنید.',
                ]);
            }

            if ($ownerId !== null) {
                $this->tenant->owner((int) $locked->organization_id, $ownerId);
            }

            if ($current === $ownerId) {
                return $locked;
            }

            $previous = $locked->auditSnapshot();
            $this->concurrency->bump($locked, [
                'owner_organization_user_id' => $ownerId,
                'last_activity_at' => now(),
            ]);
            $fresh = $locked->fresh('owner') ?? $locked;
            $this->audit->record($fresh, DealActivityType::OwnerChanged, $actor->id, 'manual', $previous, $fresh->auditSnapshot());
            $this->audit->afterCommit(new DealOwnerChanged(
                (int) $fresh->organization_id,
                (int) $fresh->id,
                $actor->id,
                'manual',
                $current,
                $ownerId,
            ));

            return $fresh;
        });
    }
}
