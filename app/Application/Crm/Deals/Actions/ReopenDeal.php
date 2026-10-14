<?php

namespace App\Application\Crm\Deals\Actions;

use App\Application\Crm\Deals\DealAudit;
use App\Application\Crm\Deals\DealAuthorization;
use App\Domain\Crm\Deals\DealActivityType;
use App\Domain\Crm\Deals\DealStatus;
use App\Domain\Crm\Deals\Events\DealReopened;
use App\Models\CrmDeal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReopenDeal
{
    public function __construct(
        private DealAuthorization $auth,
        private DealAudit $audit,
    ) {}

    public function handle(CrmDeal $deal, User $actor): CrmDeal
    {
        $this->auth->authorize($this->auth->canReopen($actor, $deal));

        return DB::transaction(function () use ($deal, $actor): CrmDeal {
            $locked = CrmDeal::query()->whereKey($deal->id)->lockForUpdate()->first();

            if (! $locked instanceof CrmDeal) {
                throw new \RuntimeException('Deal disappeared before reopen.');
            }

            if ($locked->status->isOpen()) {
                return $locked;
            }

            $stage = $locked->stage;

            if (! $stage || ! $stage->is_active) {
                throw ValidationException::withMessages([
                    'stage' => 'مرحله فعلی غیرفعال است. ابتدا آن مرحله را فعال کنید.',
                ]);
            }

            $fromStatus = $locked->status->value;
            $previous = $locked->auditSnapshot();
            $locked->update([
                'status' => DealStatus::Open,
                'closed_at' => null,
                'lost_reason' => null,
                'last_activity_at' => now(),
            ]);
            $fresh = $locked->fresh() ?? $locked;
            $this->audit->stage($fresh, $stage, $stage, $fromStatus, DealStatus::Open->value, $actor->id, 'manual');
            $this->audit->record($fresh, DealActivityType::Reopened, $actor->id, 'manual', $previous, $fresh->auditSnapshot());
            $this->audit->afterCommit(new DealReopened(
                (int) $fresh->organization_id,
                (int) $fresh->id,
                $actor->id,
                'manual',
                $fromStatus,
            ));

            return $fresh;
        });
    }
}
