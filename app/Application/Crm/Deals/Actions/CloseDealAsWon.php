<?php

namespace App\Application\Crm\Deals\Actions;

use App\Application\Crm\Deals\DealAudit;
use App\Application\Crm\Deals\DealAuthorization;
use App\Application\Crm\Deals\DealConcurrency;
use App\Domain\Crm\Deals\DealActivityType;
use App\Domain\Crm\Deals\DealCloseReasonKind;
use App\Domain\Crm\Deals\DealStatus;
use App\Domain\Crm\Deals\Events\DealWon;
use App\Models\CrmDeal;
use App\Models\CrmDealCloseReason;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CloseDealAsWon
{
    public function __construct(
        private DealAuthorization $auth,
        private DealAudit $audit,
        private DealConcurrency $concurrency,
        private EnsureDealCloseReasons $reasons,
    ) {}

    public function handle(
        CrmDeal $deal,
        User $actor,
        ?string $reasonCode = null,
        ?string $wonReason = null,
        mixed $expectedVersion = null,
    ): CrmDeal {
        $this->auth->authorize($this->auth->canClose($actor, $deal));
        $this->reasons->handle((int) $deal->organization_id);

        $wonReason = trim((string) $wonReason);
        $wonReason = $wonReason === '' ? null : $wonReason;
        $reasonCode = trim((string) $reasonCode);
        $reasonCode = $reasonCode === '' ? null : $reasonCode;

        if ($wonReason !== null && mb_strlen($wonReason) > 500) {
            throw ValidationException::withMessages([
                'won_reason' => 'توضیح موفقیت حداکثر ۵۰۰ نویسه دارد.',
            ]);
        }

        if ($reasonCode !== null) {
            $catalog = CrmDealCloseReason::query()
                ->where('organization_id', $deal->organization_id)
                ->where('kind', DealCloseReasonKind::Won)
                ->where('code', $reasonCode)
                ->where('is_active', true)
                ->first();

            if (! $catalog instanceof CrmDealCloseReason) {
                throw ValidationException::withMessages([
                    'won_reason_code' => 'دلیل موفقیت معتبر نیست.',
                ]);
            }

            if ($catalog->requires_note && blank($wonReason)) {
                throw ValidationException::withMessages([
                    'won_reason' => 'برای این دلیل، توضیح الزامی است.',
                ]);
            }

            $wonReason ??= $catalog->label;
        }

        return DB::transaction(function () use ($deal, $actor, $reasonCode, $wonReason, $expectedVersion): CrmDeal {
            $locked = CrmDeal::query()->whereKey($deal->id)->lockForUpdate()->first();

            if (! $locked instanceof CrmDeal) {
                throw new \RuntimeException('Deal disappeared before close.');
            }

            $this->concurrency->assertExpectedVersion($locked, $expectedVersion);

            if ($locked->status === DealStatus::Won) {
                return $locked;
            }

            if ($locked->status === DealStatus::Lost) {
                throw ValidationException::withMessages([
                    'status' => 'معامله ناموفق را ابتدا بازگشایی کنید.',
                ]);
            }

            $previous = $locked->auditSnapshot();
            $stage = $locked->stage;
            $this->concurrency->bump($locked, [
                'status' => DealStatus::Won,
                'closed_at' => now(),
                'lost_reason' => null,
                'lost_reason_code' => null,
                'won_reason' => $wonReason,
                'last_activity_at' => now(),
            ]);
            $fresh = $locked->fresh() ?? $locked;
            $this->audit->stage($fresh, $stage, $stage, DealStatus::Open->value, DealStatus::Won->value, $actor->id, 'manual');
            $this->audit->record($fresh, DealActivityType::Won, $actor->id, 'manual', $previous, $fresh->auditSnapshot());
            $this->audit->afterCommit(new DealWon((int) $fresh->organization_id, (int) $fresh->id, $actor->id, 'manual'));

            return $fresh;
        });
    }
}
