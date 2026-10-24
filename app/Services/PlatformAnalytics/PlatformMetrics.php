<?php

namespace App\Services\PlatformAnalytics;

use App\Domain\Call\Enums\CallProcessingStatus;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Organization;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Morilog\Jalali\Jalalian;

/**
 * An organization is "active" in a month when at least one of its calls was analyzed in it.
 * Demo organizations are excluded from every metric.
 */
class PlatformMetrics
{
    public const ACTIVATION_DAYS = 7;

    /** @var array<string, list<int>> */
    private array $activeIdsCache = [];

    /** @var array<int, Carbon>|null */
    private ?array $firstAnalysisAt = null;

    /**
     * Jalali months, oldest first, ending with the month containing $now.
     *
     * @return list<array{key: string, label: string, start: Carbon, end: Carbon}>
     */
    public function months(int $count, ?CarbonInterface $now = null): array
    {
        $current = Jalalian::fromCarbon(Carbon::parse($now ?? now()))->getFirstDayOfMonth();
        $months = [];

        for ($offset = $count - 1; $offset >= 0; $offset--) {
            $month = $offset === 0 ? $current : $current->subMonths($offset);
            $start = $month->toCarbon()->startOfDay();
            $end = $month->addMonths(1)->toCarbon()->startOfDay();

            $months[] = [
                'key' => $month->format('Y-m'),
                'label' => $month->format('F Y'),
                'start' => $start,
                'end' => $end,
            ];
        }

        return $months;
    }

    /** @return list<int> */
    public function activeOrganizationIds(CarbonInterface $start, CarbonInterface $end): array
    {
        $key = $start->getTimestamp().'-'.$end->getTimestamp();

        return $this->activeIdsCache[$key] ??= ConversationAnalysis::query()
            ->whereIn('organization_id', $this->realOrganizations()->select('id'))
            ->where('analyzed_at', '>=', $start)
            ->where('analyzed_at', '<', $end)
            ->distinct()
            ->pluck('organization_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<array{
     *     key: string, label: string, start: Carbon, end: Carbon, is_current: bool,
     *     active: int, new: int, churned: int, reactivated: int, churn_rate: float|null
     * }>
     */
    public function monthlySeries(int $count = 6, ?CarbonInterface $now = null): array
    {
        $months = $this->months($count + 1, $now);
        $firstAnalysis = $this->firstAnalysisAt();
        $series = [];

        for ($i = 1; $i < count($months); $i++) {
            $previous = $months[$i - 1];
            $month = $months[$i];

            $previousActive = $this->activeOrganizationIds($previous['start'], $previous['end']);
            $active = $this->activeOrganizationIds($month['start'], $month['end']);

            $churned = array_diff($previousActive, $active);
            $reactivated = array_filter(
                array_diff($active, $previousActive),
                fn (int $id): bool => isset($firstAnalysis[$id]) && $firstAnalysis[$id]->lt($previous['start']),
            );

            $series[] = [
                ...$month,
                'is_current' => $i === count($months) - 1,
                'active' => count($active),
                'new' => $this->realOrganizations()
                    ->where('created_at', '>=', $month['start'])
                    ->where('created_at', '<', $month['end'])
                    ->count(),
                'churned' => count($churned),
                'reactivated' => count($reactivated),
                'churn_rate' => $previousActive === [] ? null : count($churned) / count($previousActive),
            ];
        }

        return $series;
    }

    /**
     * Share of organizations created in the last $lookbackDays (and old enough to be judged)
     * whose first analyzed call happened within ACTIVATION_DAYS of signup.
     *
     * @return array{eligible: int, activated: int, rate: float|null, median_days_to_first_value: float|null}
     */
    public function activation(int $lookbackDays = 90, ?CarbonInterface $now = null): array
    {
        $now = Carbon::parse($now ?? now());
        $firstAnalysis = $this->firstAnalysisAt();

        $organizations = $this->realOrganizations()
            ->where('created_at', '>=', $now->copy()->subDays($lookbackDays))
            ->where('created_at', '<=', $now->copy()->subDays(self::ACTIVATION_DAYS))
            ->get(['id', 'created_at']);

        $activated = 0;
        $daysToFirstValue = [];

        foreach ($organizations as $organization) {
            $first = $firstAnalysis[$organization->id] ?? null;

            if (! $first) {
                continue;
            }

            $days = max(0, $organization->created_at->diffInHours($first) / 24);
            $daysToFirstValue[] = $days;

            if ($days <= self::ACTIVATION_DAYS) {
                $activated++;
            }
        }

        return [
            'eligible' => $organizations->count(),
            'activated' => $activated,
            'rate' => $organizations->isEmpty() ? null : $activated / $organizations->count(),
            'median_days_to_first_value' => $this->median($daysToFirstValue),
        ];
    }

    /**
     * Signup-month cohorts and the share of each cohort active in later months.
     * retention[0] is the signup month itself.
     *
     * @return list<array{label: string, size: int, retention: list<float|null>}>
     */
    public function cohorts(int $count = 6, ?CarbonInterface $now = null): array
    {
        $months = $this->months($count, $now);
        $cohorts = [];

        foreach ($months as $index => $month) {
            $members = $this->realOrganizations()
                ->where('created_at', '>=', $month['start'])
                ->where('created_at', '<', $month['end'])
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $retention = [];

            for ($later = $index; $later < count($months); $later++) {
                if ($members === []) {
                    $retention[] = null;

                    continue;
                }

                $active = $this->activeOrganizationIds($months[$later]['start'], $months[$later]['end']);
                $retention[] = count(array_intersect($members, $active)) / count($members);
            }

            $cohorts[] = [
                'label' => $month['label'],
                'size' => count($members),
                'retention' => $retention,
            ];
        }

        return $cohorts;
    }

    /** @return array{total: int, analyzed: int, failed: int, success_rate: float|null} */
    public function processing(CarbonInterface $start, CarbonInterface $end): array
    {
        $counts = Call::query()
            ->whereIn('organization_id', $this->realOrganizations()->select('id'))
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->selectRaw('processing_status, COUNT(*) as aggregate')
            ->groupBy('processing_status')
            ->pluck('aggregate', 'processing_status');

        $analyzed = (int) ($counts[CallProcessingStatus::Analyzed->value] ?? 0);
        $failed = (int) ($counts[CallProcessingStatus::Failed->value] ?? 0);
        $finished = $analyzed + $failed;

        return [
            'total' => (int) $counts->sum(),
            'analyzed' => $analyzed,
            'failed' => $failed,
            'success_rate' => $finished === 0 ? null : $analyzed / $finished,
        ];
    }

    /** @return Builder<Organization> */
    public function realOrganizations(): Builder
    {
        return Organization::query()->where('is_demo', false);
    }

    /** @return array<int, Carbon> */
    private function firstAnalysisAt(): array
    {
        return $this->firstAnalysisAt ??= ConversationAnalysis::query()
            ->whereIn('organization_id', $this->realOrganizations()->select('id'))
            ->selectRaw('organization_id, MIN(analyzed_at) as first_at')
            ->groupBy('organization_id')
            ->get()
            ->mapWithKeys(fn ($row): array => [(int) $row->organization_id => Carbon::parse($row->first_at)])
            ->all();
    }

    /** @param list<float> $values */
    private function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 1
            ? $values[$middle]
            : ($values[$middle - 1] + $values[$middle]) / 2;
    }
}
