<?php

namespace App\Services\Billing;

use App\Domain\Platform\Enums\SubscriptionStatus;
use App\Models\AdminSetting;
use App\Models\AiCostRate;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\ConversationAnalysis;
use App\Models\DemoRequest;
use App\Models\MarketingSpend;
use App\Models\Organization;
use App\Models\Subscription;
use App\Services\PlatformAnalytics\PlatformMetrics;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Revenue metrics are based on monthly recurring revenue (MRR) rebuilt from subscription
 * start/end dates. Cash metrics are based on recorded payments. All amounts are rials.
 */
class BillingMetrics
{
    public function __construct(private readonly PlatformMetrics $platform) {}

    /** @return array<int, int> MRR per organization at the given moment */
    public function mrrByOrganization(CarbonInterface $moment): array
    {
        $result = [];

        Subscription::query()
            ->payingAt($moment)
            ->whereIn('organization_id', $this->platform->realOrganizations()->select('id'))
            ->get(['organization_id', 'status', 'price_rials', 'billing_period'])
            ->each(function (Subscription $subscription) use (&$result): void {
                $result[$subscription->organization_id] = ($result[$subscription->organization_id] ?? 0) + $subscription->monthlyRevenue();
            });

        return array_filter($result);
    }

    public function mrrAt(CarbonInterface $moment): int
    {
        return array_sum($this->mrrByOrganization($moment));
    }

    /**
     * MRR movement per Jalali month: new, expansion, contraction, churned and reactivated revenue.
     *
     * @return list<array{label: string, start_mrr: int, end_mrr: int, new: int, expansion: int, contraction: int, churned: int, reactivated: int, churned_customers: int, net_revenue_retention: float|null}>
     */
    public function mrrMovement(int $months = 6, ?CarbonInterface $now = null): array
    {
        $now = Carbon::parse($now ?? now());
        $firstPaidAt = Subscription::query()
            ->where('status', '!=', SubscriptionStatus::Trial->value)
            ->selectRaw('organization_id, MIN(starts_at) as first_at')
            ->groupBy('organization_id')
            ->pluck('first_at', 'organization_id')
            ->map(fn ($value): Carbon => Carbon::parse($value));

        $rows = [];

        foreach ($this->platform->months($months, $now) as $month) {
            $end = $month['end']->lt($now) ? $month['end']->copy()->subSecond() : $now;
            $before = $this->mrrByOrganization($month['start']->copy()->subSecond());
            $after = $this->mrrByOrganization($end);

            $row = ['new' => 0, 'expansion' => 0, 'contraction' => 0, 'churned' => 0, 'reactivated' => 0, 'churned_customers' => 0];

            foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $organizationId) {
                $old = $before[$organizationId] ?? 0;
                $new = $after[$organizationId] ?? 0;

                if ($old === 0 && $new > 0) {
                    $isReturning = isset($firstPaidAt[$organizationId]) && $firstPaidAt[$organizationId]->lt($month['start']);
                    $row[$isReturning ? 'reactivated' : 'new'] += $new;
                } elseif ($old > 0 && $new === 0) {
                    $row['churned'] += $old;
                    $row['churned_customers']++;
                } elseif ($new > $old) {
                    $row['expansion'] += $new - $old;
                } elseif ($new < $old) {
                    $row['contraction'] += $old - $new;
                }
            }

            $startMrr = array_sum($before);

            $rows[] = [
                'label' => $month['label'],
                'start_mrr' => $startMrr,
                'end_mrr' => array_sum($after),
                ...$row,
                'net_revenue_retention' => $startMrr === 0 ? null : ($startMrr + $row['expansion'] - $row['contraction'] - $row['churned']) / $startMrr,
            ];
        }

        return $rows;
    }

    /**
     * @return array{mrr: int, arr: int, paying_customers: int, arpa: int|null, cash_this_month: int, cash_last_month: int,
     *     outstanding: int, overdue: int, overdue_customers: int, renewals_due: int, trials_ending: int,
     *     revenue_churn_rate: float|null, net_revenue_retention: float|null, ltv: int|null}
     */
    public function overview(?CarbonInterface $now = null): array
    {
        $now = Carbon::parse($now ?? now());
        $mrrByOrganization = $this->mrrByOrganization($now);
        $mrr = array_sum($mrrByOrganization);
        $paying = count($mrrByOrganization);
        $arpa = $paying === 0 ? null : intdiv($mrr, $paying);

        [$lastMonth, $thisMonth] = $this->platform->months(2, $now);
        $lastMovement = $this->mrrMovement(2, $now)[0];
        $revenueChurn = $lastMovement['start_mrr'] === 0 ? null : $lastMovement['churned'] / $lastMovement['start_mrr'];

        $outstanding = BillingInvoice::query()->outstanding()->with('payments')->get();
        $overdue = $outstanding->filter(fn (BillingInvoice $invoice): bool => $invoice->isOverdue());

        return [
            'mrr' => $mrr,
            'arr' => $mrr * 12,
            'paying_customers' => $paying,
            'arpa' => $arpa,
            'cash_this_month' => $this->cashBetween($thisMonth['start'], $thisMonth['end']),
            'cash_last_month' => $this->cashBetween($lastMonth['start'], $lastMonth['end']),
            'outstanding' => (int) $outstanding->sum(fn (BillingInvoice $invoice): int => $invoice->balanceRials()),
            'overdue' => (int) $overdue->sum(fn (BillingInvoice $invoice): int => $invoice->balanceRials()),
            'overdue_customers' => $overdue->pluck('organization_id')->unique()->count(),
            'renewals_due' => Subscription::query()
                ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::PastDue->value])
                ->whereNull('cancelled_at')
                ->whereBetween('current_period_ends_at', [$now, $now->copy()->addDays(14)])
                ->count(),
            'trials_ending' => Subscription::query()
                ->where('status', SubscriptionStatus::Trial->value)
                ->whereBetween('trial_ends_at', [$now, $now->copy()->addDays(7)])
                ->count(),
            'revenue_churn_rate' => $revenueChurn,
            'net_revenue_retention' => $lastMovement['net_revenue_retention'],
            'ltv' => ($arpa !== null && $revenueChurn) ? (int) round($arpa / $revenueChurn) : null,
        ];
    }

    public function cashBetween(CarbonInterface $start, CarbonInterface $end): int
    {
        return (int) BillingPayment::query()
            ->where('paid_at', '>=', $start)
            ->where('paid_at', '<', $end)
            ->get(['type', 'amount_rials'])
            ->sum(fn (BillingPayment $payment): int => $payment->signedAmount());
    }

    /**
     * Unpaid balance of issued invoices grouped by days past due.
     *
     * @return array<string, array{count: int, amount: int}>
     */
    public function receivablesAging(?CarbonInterface $now = null): array
    {
        $now = Carbon::parse($now ?? now());
        $buckets = array_fill_keys(['not_due', '0_30', '31_60', '61_90', '90_plus'], ['count' => 0, 'amount' => 0]);

        foreach (BillingInvoice::query()->outstanding()->with('payments')->get() as $invoice) {
            $balance = $invoice->balanceRials();

            if ($balance === 0) {
                continue;
            }

            $daysLate = $invoice->due_at === null || $invoice->due_at->gte($now) ? -1 : (int) $invoice->due_at->diffInDays($now);
            $bucket = match (true) {
                $daysLate < 0 => 'not_due',
                $daysLate <= 30 => '0_30',
                $daysLate <= 60 => '31_60',
                $daysLate <= 90 => '61_90',
                default => '90_plus',
            };

            $buckets[$bucket]['count']++;
            $buckets[$bucket]['amount'] += $balance;
        }

        return $buckets;
    }

    /**
     * AI cost per organization between two moments, using the rate effective for each model at $end.
     *
     * @return array<int, int> rials per organization
     */
    public function aiCostByOrganization(CarbonInterface $start, CarbonInterface $end): array
    {
        $rates = AiCostRate::query()
            ->where('effective_from', '<=', $end->toDateString())
            ->orderBy('effective_from')
            ->get()
            ->keyBy('model_name');

        if ($rates->isEmpty()) {
            return [];
        }

        $costs = [];

        ConversationAnalysis::query()
            ->where('analyzed_at', '>=', $start)
            ->where('analyzed_at', '<', $end)
            ->groupBy('organization_id', 'model_name')
            ->selectRaw('organization_id, model_name, SUM(input_tokens) as input_tokens, SUM(output_tokens) as output_tokens')
            ->toBase()
            ->get()
            ->each(function (object $row) use ($rates, &$costs): void {
                $rate = $rates->get($row->model_name);

                if (! $rate) {
                    return;
                }

                $cost = ($row->input_tokens * $rate->input_cost_per_million_rials + $row->output_tokens * $rate->output_cost_per_million_rials) / 1_000_000;
                $costs[(int) $row->organization_id] = ($costs[(int) $row->organization_id] ?? 0) + (int) round($cost);
            });

        return $costs;
    }

    /**
     * Revenue (MRR at month end) against AI cost for the current Jalali month, per organization.
     *
     * @return array{total_cost: int, total_revenue: int, budget: int|null, rows: list<array{organization_id: int, title: string, revenue: int, cost: int, margin: int, margin_rate: float|null}>}
     */
    public function margins(?CarbonInterface $now = null): array
    {
        $now = Carbon::parse($now ?? now());
        [$month] = $this->platform->months(1, $now);

        $costs = $this->aiCostByOrganization($month['start'], $now);
        $revenue = $this->mrrByOrganization($now);
        $ids = array_unique([...array_keys($costs), ...array_keys($revenue)]);
        $titles = Organization::query()->whereKey($ids)->pluck('title', 'id');

        $rows = collect($ids)
            ->map(function (int $id) use ($costs, $revenue, $titles): array {
                $cost = $costs[$id] ?? 0;
                $income = $revenue[$id] ?? 0;

                return [
                    'organization_id' => $id,
                    'title' => (string) ($titles[$id] ?? '#'.$id),
                    'revenue' => $income,
                    'cost' => $cost,
                    'margin' => $income - $cost,
                    'margin_rate' => $income === 0 ? null : ($income - $cost) / $income,
                ];
            })
            ->sortBy('margin')
            ->values()
            ->all();

        $budget = AdminSetting::valueFor(AdminSetting::MONTHLY_AI_BUDGET_RIALS);

        return [
            'total_cost' => array_sum($costs),
            'total_revenue' => array_sum($revenue),
            'budget' => $budget === null ? null : (int) $budget,
            'rows' => $rows,
        ];
    }

    /**
     * Current subscriptions with a call quota, and their use in the current billing period.
     *
     * @return list<array{organization_id: int, title: string, plan: string, usage: int, quota: int, rate: float}>
     */
    public function quotaUsage(float $threshold = 0.8): array
    {
        $rows = [];

        Subscription::query()
            ->current()
            ->whereHas('plan', fn ($query) => $query->whereNotNull('call_quota')->where('call_quota', '>', 0))
            ->with(['plan', 'organization:id,title'])
            ->get()
            ->each(function (Subscription $subscription) use (&$rows, $threshold): void {
                $usage = $this->usageInCurrentPeriod($subscription);
                $rate = $usage / $subscription->plan->call_quota;

                if ($rate >= $threshold) {
                    $rows[] = [
                        'organization_id' => $subscription->organization_id,
                        'title' => (string) $subscription->organization?->title,
                        'plan' => $subscription->plan->name,
                        'usage' => $usage,
                        'quota' => $subscription->plan->call_quota,
                        'rate' => $rate,
                    ];
                }
            });

        usort($rows, fn (array $a, array $b): int => $b['rate'] <=> $a['rate']);

        return $rows;
    }

    public function usageInCurrentPeriod(Subscription $subscription): int
    {
        $periodEnd = $subscription->current_period_ends_at ?? now();
        $periodStart = $subscription->status === SubscriptionStatus::Trial
            ? $subscription->starts_at
            : $periodEnd->copy()->subMonths($subscription->billing_period->months());

        return ConversationAnalysis::query()
            ->where('organization_id', $subscription->organization_id)
            ->where('analyzed_at', '>=', $periodStart->max($subscription->starts_at))
            ->where('analyzed_at', '<', $periodEnd)
            ->count();
    }

    /**
     * @return array{plan: string|null, status: SubscriptionStatus|null, period_ends_at: mixed, mrr: int, outstanding_rials: int, overdue_invoices: int, usage: int, quota: int|null}
     */
    public function organizationSummary(Organization $organization): array
    {
        $subscription = $organization->currentSubscription()->with('plan')->first();
        $outstanding = $organization->billingInvoices()->outstanding()->with('payments')->get();

        return [
            'plan' => $subscription?->plan?->name,
            'status' => $subscription?->status,
            'period_ends_at' => $subscription?->current_period_ends_at,
            'mrr' => $subscription?->monthlyRevenue() ?? 0,
            'outstanding_rials' => (int) $outstanding->sum(fn (BillingInvoice $invoice): int => $invoice->balanceRials()),
            'overdue_invoices' => $outstanding->filter(fn (BillingInvoice $invoice): bool => $invoice->isOverdue())->count(),
            'usage' => $subscription ? $this->usageInCurrentPeriod($subscription) : 0,
            'quota' => $subscription?->plan?->call_quota,
        ];
    }

    /**
     * Customer acquisition cost per Jalali month: marketing spend divided by organizations
     * that started paying for the first time that month.
     *
     * @return list<array{label: string, spend: int, new_customers: int, cac: int|null, payback_months: float|null, by_channel: array<string, array{spend: int, customers: int}>}>
     */
    public function acquisition(int $months = 6, ?CarbonInterface $now = null): array
    {
        $now = Carbon::parse($now ?? now());
        $arpa = $this->overview($now)['arpa'];

        $firstPaid = Subscription::query()
            ->where('status', '!=', SubscriptionStatus::Trial->value)
            ->whereIn('organization_id', $this->platform->realOrganizations()->select('id'))
            ->selectRaw('organization_id, MIN(starts_at) as first_at')
            ->groupBy('organization_id')
            ->pluck('first_at', 'organization_id')
            ->map(fn ($value): Carbon => Carbon::parse($value));

        $sourceByOrganization = DemoRequest::query()
            ->whereNotNull('converted_organization_id')
            ->pluck('source', 'converted_organization_id');

        $rows = [];

        foreach ($this->platform->months($months, $now) as $month) {
            $spends = MarketingSpend::query()
                ->where('spent_on', '>=', $month['start']->toDateString())
                ->where('spent_on', '<', $month['end']->toDateString())
                ->get(['channel', 'amount_rials']);

            $newCustomers = $firstPaid->filter(fn (Carbon $at): bool => $at->gte($month['start']) && $at->lt($month['end']))->keys();
            $spend = (int) $spends->sum('amount_rials');
            $cac = $newCustomers->isEmpty() ? null : intdiv($spend, $newCustomers->count());

            $byChannel = [];

            foreach ($spends->groupBy('channel') as $channel => $items) {
                $byChannel[$channel] = [
                    'spend' => (int) $items->sum('amount_rials'),
                    'customers' => $newCustomers->filter(fn ($id): bool => ($sourceByOrganization[$id] ?? null) === $channel)->count(),
                ];
            }

            $rows[] = [
                'label' => $month['label'],
                'spend' => $spend,
                'new_customers' => $newCustomers->count(),
                'cac' => $cac,
                'payback_months' => ($cac !== null && $arpa) ? $cac / $arpa : null,
                'by_channel' => $byChannel,
            ];
        }

        return $rows;
    }

    /** Invoices issued but unpaid whose due date has passed. */
    public function overdueInvoicesCount(): int
    {
        return BillingInvoice::query()->overdue()->count();
    }
}
