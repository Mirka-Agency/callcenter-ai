<?php

namespace App\Application\Crm\Deals\Actions;

use App\Application\Crm\Deals\DealAuthorization;
use App\Domain\Crm\Deals\DealBarrierStatus;
use App\Models\CrmDealBarrier;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ResolveDealBarrier
{
    public function __construct(private DealAuthorization $auth) {}

    public function handle(CrmDealBarrier $barrier, User $actor, DealBarrierStatus $status): CrmDealBarrier
    {
        $deal = $barrier->deal;
        $this->auth->authorize($deal !== null && $this->auth->canUpdate($actor, $deal));

        if (! in_array($status, [DealBarrierStatus::Confirmed, DealBarrierStatus::Dismissed], true)) {
            throw ValidationException::withMessages([
                'barrier' => 'وضعیت مانع معتبر نیست.',
            ]);
        }

        $barrier->update([
            'status' => $status,
            'resolved_by_user_id' => $actor->id,
            'resolved_at' => now(),
        ]);

        return $barrier->fresh() ?? $barrier;
    }
}
