<?php

namespace App\Application\Crm\Deals\Actions;

use App\Application\Crm\Deals\DealAudit;
use App\Application\Crm\Deals\DealAuthorization;
use App\Domain\Crm\Deals\DealActivityType;
use App\Models\CrmDeal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ArchiveDeal
{
    public function __construct(
        private DealAuthorization $auth,
        private DealAudit $audit,
    ) {}

    public function handle(CrmDeal $deal, User $actor): CrmDeal
    {
        $this->auth->authorize($this->auth->canArchive($actor, $deal));

        return DB::transaction(function () use ($deal, $actor): CrmDeal {
            $locked = CrmDeal::withTrashed()->whereKey($deal->id)->lockForUpdate()->first();

            if (! $locked instanceof CrmDeal) {
                throw new \RuntimeException('Deal disappeared before archive.');
            }

            if ($locked->trashed()) {
                return $locked;
            }

            $previous = $locked->auditSnapshot();
            $locked->update(['last_activity_at' => now()]);
            $this->audit->record($locked, DealActivityType::Archived, $actor->id, 'manual', $previous, $locked->auditSnapshot());
            $locked->delete();

            return $locked;
        });
    }
}
