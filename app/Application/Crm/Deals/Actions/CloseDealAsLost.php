<?php

namespace App\Application\Crm\Deals\Actions;

use App\Application\Crm\Deals\DealAudit;
use App\Application\Crm\Deals\DealAuthorization;
use App\Application\Crm\Deals\DealConcurrency;
use App\Domain\Crm\Deals\DealActivityType;
use App\Domain\Crm\Deals\DealCloseReasonKind;
use App\Domain\Crm\Deals\DealStatus;
use App\Domain\Crm\Deals\Events\DealLost;
use App\Models\CrmDeal;
use App\Models\CrmDealCloseReason;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CloseDealAsLost
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
        string $reason,
        ?string $reasonCode = null,
        mixed $expectedVersion = null,
    ): CrmDeal {
        $this->auth->authorize($this->auth->canClose($actor, $deal));
        $this->reasons->handle((int) $deal->organization_id);

        $reason = trim($reason);
        $reasonCode = trim((string) $reasonCode);
        $reasonCode = $reasonCode === '' ? null : $reasonCode;

        if ($reasonCode !== null) {
            $catalog = CrmDealCloseReason::query()
                ->where('organization_id', $deal->organization_id)
                ->where('kind', DealCloseReasonKind::Lost)
                ->where('code', $reasonCode)
                ->where('is_active', true)
                ->first();

            if (! $catalog instanceof CrmDealCloseReason) {
                throw ValidationException::withMessages([
                    'lost_reason_code' => 'دلیل شکست معتبر نیست.',
                ]);
            }

            if ($catalog->requires_note && ($reason === '' || mb_strlen($reason) > 500)) {
                throw ValidationException::withMessages([
                    'lost_reason' => 'برای این دلیل، توضیح الزامی است و حداکثر ۵۰۰ نویسه دارد.',
                ]);
            }

            if ($reason === '') {
                $reason = $catalog->label;
            }
        }

        if ($reason === '' || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages([
                'lost_reason' => 'دلیل ناموفق بودن معامله الزامی است و حداکثر ۵۰۰ نویسه دارد.',
            ]);
        }

        return DB::transaction(function () use ($deal, $actor, $reason, $reasonCode, $expectedVersion): CrmDeal {
            $locked = CrmDeal::query()->whereKey($deal->id)->lockForUpdate()->first();

            if (! $locked instanceof CrmDeal) {
                throw new \RuntimeException('Deal disappeared before close.');
            }

            $this->concurrency->assertExpectedVersion($locked, $expectedVersion);

            if ($locked->status === DealStatus::Lost && $locked->lost_reason === $reason && $locked->lost_reason_code === $reasonCode) {
                return $locked;
            }

            if ($locked->status === DealStatus::Won) {
                throw ValidationException::withMessages([
                    'status' => 'معامله موفق را ابتدا بازگشایی کنید.',
                ]);
            }

            if ($locked->status === DealStatus::Lost) {
                throw ValidationException::withMessages([
                    'status' => 'معامله بسته‌شده را نمی‌توان در این وضعیت تغییر داد. ابتدا آن را بازگشایی کنید.',
                ]);
            }

            $previous = $locked->auditSnapshot();
            $stage = $locked->stage;
            $this->concurrency->bump($locked, [
                'status' => DealStatus::Lost,
                'closed_at' => now(),
                'lost_reason' => $reason,
                'lost_reason_code' => $reasonCode,
                'won_reason' => null,
                'last_activity_at' => now(),
            ]);
            $fresh = $locked->fresh() ?? $locked;
            $this->audit->stage($fresh, $stage, $stage, DealStatus::Open->value, DealStatus::Lost->value, $actor->id, 'manual');
            $this->audit->record($fresh, DealActivityType::Lost, $actor->id, 'manual', $previous, $fresh->auditSnapshot());
            $this->audit->afterCommit(new DealLost((int) $fresh->organization_id, (int) $fresh->id, $actor->id, 'manual', $reason));

            return $fresh;
        });
    }
}
