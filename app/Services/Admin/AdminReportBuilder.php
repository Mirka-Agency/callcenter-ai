<?php

namespace App\Services\Admin;

use App\Models\ConsultationRequest;
use App\Models\ContactMessage;
use App\Models\DemoRequest;
use App\Services\Billing\BillingMetrics;
use App\Services\PlatformAnalytics\NpsMetrics;
use App\Services\PlatformAnalytics\OrganizationHealth;
use App\Services\PlatformAnalytics\OrganizationHealthScorer;
use App\Services\PlatformAnalytics\PlatformMetrics;
use App\Support\JalaliDate;
use App\Support\Money;
use App\Support\PersianNumber;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Builds the weekly or monthly management summary e-mailed to admins.
 */
class AdminReportBuilder
{
    public const WEEKLY = 'weekly';

    public const MONTHLY = 'monthly';

    public function __construct(
        private readonly PlatformMetrics $platform,
        private readonly BillingMetrics $billing,
        private readonly NpsMetrics $nps,
        private readonly OrganizationHealthScorer $health,
        private readonly ProblemInbox $inbox,
    ) {}

    /**
     * @return array{period: string, title: string, range: string, sections: list<array{heading: string, rows: array<string, string>}>}
     */
    public function build(string $period = self::WEEKLY, ?CarbonInterface $now = null): array
    {
        $end = Carbon::parse($now ?? now());
        $start = $period === self::MONTHLY ? $end->copy()->subMonth() : $end->copy()->subWeek();
        $previousStart = $period === self::MONTHLY ? $start->copy()->subMonth() : $start->copy()->subWeek();

        $active = count($this->platform->activeOrganizationIds($start, $end));
        $previousActive = count($this->platform->activeOrganizationIds($previousStart, $start));
        $processing = $this->platform->processing($start, $end);
        $newOrganizations = $this->platform->realOrganizations()->whereBetween('created_at', [$start, $end])->count();
        $overview = $this->billing->overview($end);
        $nps = $this->nps->summary($end);
        $critical = collect($this->health->at($end)->all())->filter(fn (OrganizationHealth $health): bool => $health->band() === OrganizationHealth::CRITICAL)->count();
        $leads = collect([DemoRequest::class, ConsultationRequest::class, ContactMessage::class])
            ->sum(fn (string $model): int => $model::query()->whereBetween('created_at', [$start, $end])->count());
        $breaching = collect([DemoRequest::class, ConsultationRequest::class, ContactMessage::class])
            ->sum(fn (string $model): int => $model::query()->breachingSla()->count());

        return [
            'period' => $period,
            'title' => __('admin.reports.title_'.$period),
            'range' => JalaliDate::date($start).' – '.JalaliDate::date($end),
            'sections' => [
                [
                    'heading' => __('admin.reports.usage'),
                    'rows' => [
                        __('admin.reports.active_organizations') => $this->number($active).' '.$this->change($active, $previousActive),
                        __('admin.reports.new_organizations') => $this->number($newOrganizations),
                        __('admin.reports.calls_processed') => $this->number($processing['total']),
                        __('admin.reports.success_rate') => $this->percent($processing['success_rate']),
                        __('admin.reports.critical_health') => $this->number($critical),
                    ],
                ],
                [
                    'heading' => __('admin.reports.revenue'),
                    'rows' => [
                        __('admin.billing.mrr') => Money::format($overview['mrr']),
                        __('admin.reports.paying_customers') => $this->number($overview['paying_customers']),
                        __('admin.reports.cash_collected') => Money::format($this->billing->cashBetween($start, $end)),
                        __('admin.finance.outstanding') => Money::format($overview['outstanding']),
                        __('admin.reports.overdue') => Money::format($overview['overdue']),
                        __('admin.reports.renewals_due') => $this->number($overview['renewals_due']),
                    ],
                ],
                [
                    'heading' => __('admin.reports.sales_and_support'),
                    'rows' => [
                        __('admin.reports.new_leads') => $this->number($leads),
                        __('admin.reports.leads_breaching_sla') => $this->number($breaching),
                        __('admin.reports.open_problems') => $this->number($this->inbox->count()),
                        __('admin.nps.score') => $nps['score'] === null ? '—' : $this->number($nps['score']),
                    ],
                ],
            ],
        ];
    }

    private function number(int|float|null $value): string
    {
        return $value === null ? '—' : (string) PersianNumber::format($value, null, 1);
    }

    private function percent(?float $ratio): string
    {
        return $ratio === null ? '—' : PersianNumber::format($ratio * 100, null, 1).'٪';
    }

    private function change(int $current, int $previous): string
    {
        if ($previous === 0) {
            return '';
        }

        $delta = ($current - $previous) / $previous * 100;

        return '('.($delta >= 0 ? '+' : '').PersianNumber::format($delta, null, 1).'٪)';
    }
}
