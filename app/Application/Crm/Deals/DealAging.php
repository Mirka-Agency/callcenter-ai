<?php

namespace App\Application\Crm\Deals;

use App\Domain\Crm\Deals\DealStatus;
use App\Models\CrmDeal;
use App\Models\CrmDealCall;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DealAging
{
    /** @return array<string, mixed> */
    public function forDeal(CrmDeal $deal): array
    {
        $now = now();
        $deal->loadMissing('stage');
        $stageEntered = $deal->stage_entered_at ?? $deal->created_at;
        $lastActivity = $deal->last_activity_at;
        $lastCallAt = CrmDealCall::query()
            ->where('crm_deal_id', $deal->id)
            ->latest('id')
            ->with('call:id,started_at')
            ->first()
            ?->call
            ?->started_at;

        $stageDays = $this->daysBetween($stageEntered, $now);
        $inactiveDays = $lastActivity ? $this->daysBetween($lastActivity, $now) : $stageDays;
        $threshold = $deal->stage?->stale_after_days;
        $staleByStage = $deal->status === DealStatus::Open
            && $threshold !== null
            && $stageDays !== null
            && $stageDays >= (int) $threshold;
        $staleByInactivity = $deal->status === DealStatus::Open
            && ($lastActivity === null || ($inactiveDays !== null && $inactiveDays >= 14));

        return [
            'stage_entered_at' => $stageEntered,
            'days_in_current_stage' => $stageDays,
            'last_activity_at' => $lastActivity,
            'inactive_days' => $inactiveDays,
            'last_related_call_at' => $lastCallAt,
            'stage_stale_after_days' => $threshold,
            'is_stale_by_stage' => $staleByStage,
            'is_stale_by_inactivity' => $staleByInactivity,
            'is_stale' => $staleByStage || $staleByInactivity,
            'is_at_risk' => $staleByStage || ($inactiveDays !== null && $inactiveDays >= 21),
        ];
    }

    public function isStale(CrmDeal $deal): bool
    {
        return (bool) ($this->forDeal($deal)['is_stale'] ?? false);
    }

    /**
     * Candidate SQL filter: open deals with inactivity OR any stage threshold configured.
     * Exact stage-threshold evaluation stays in {@see isStale()} / {@see filterStaleCollection()}.
     *
     * @param  Builder<CrmDeal>  $query
     * @return Builder<CrmDeal>
     */
    public function scopeStaleCandidates(Builder $query): Builder
    {
        $activityCutoff = now()->subDays(14);

        return $query->where('crm_deals.status', DealStatus::Open)
            ->where(function (Builder $outer) use ($activityCutoff): void {
                $outer->whereNull('crm_deals.last_activity_at')
                    ->orWhere('crm_deals.last_activity_at', '<', $activityCutoff)
                    ->orWhereHas('stage', fn (Builder $stage) => $stage->whereNotNull('stale_after_days'));
            });
    }

    /**
     * @param  Collection<int, CrmDeal>  $deals
     * @return Collection<int, CrmDeal>
     */
    public function filterStaleCollection(Collection $deals): Collection
    {
        return $deals->filter(fn (CrmDeal $deal) => $this->isStale($deal))->values();
    }

    private function daysBetween(?CarbonInterface $from, CarbonInterface $to): ?int
    {
        if ($from === null) {
            return null;
        }

        return max(0, (int) $from->diffInDays($to));
    }
}
