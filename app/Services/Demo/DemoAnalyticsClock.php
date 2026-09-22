<?php

namespace App\Services\Demo;

use App\DTOs\ReportFilter;
use App\Enums\ReportDatePreset;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Customer;
use App\Models\CustomerCompany;
use App\Models\Organization;
use App\Models\OrganizationActivity;
use App\Services\CustomerCompanyService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class DemoAnalyticsClock
{
    public function __construct(private CustomerCompanyService $customerCompanies) {}

    /**
     * Keep seeded demo conversations aligned with the current calendar day.
     *
     * Demo calls are dated relative to "today" at seed time. Without this shift,
     * the dashboard "تماس‌های امروز" card falls to zero while the 30-day quality
     * trend still shows recent (now stale) activity.
     */
    public function refreshIfStale(Organization $organization): int
    {
        if (! $organization->is_demo) {
            return 0;
        }

        $lock = Cache::lock('demo-analytics-clock:'.$organization->id, 30);

        if (! $lock->get()) {
            return 0;
        }

        try {
            return $this->shiftStaleTimestamps($organization);
        } finally {
            $lock->release();
        }
    }

    public function refreshAllStale(): int
    {
        return Organization::query()
            ->demo()
            ->get()
            ->filter(fn (Organization $organization) => $this->refreshIfStale($organization) !== 0)
            ->count();
    }

    private function shiftStaleTimestamps(Organization $organization): int
    {
        $latest = Call::query()
            ->where('organization_id', $organization->id)
            ->where('provider_code', 'demo')
            ->max('started_at');

        if ($latest === null) {
            return 0;
        }

        $days = $this->calendarDaysUntilToday(Carbon::parse($latest));

        if ($days === 0) {
            return 0;
        }

        $demoCalls = Call::query()
            ->where('organization_id', $organization->id)
            ->where('provider_code', 'demo')
            ->get();

        foreach ($demoCalls as $call) {
            $call->started_at = $call->started_at?->copy()->addDays($days);
            $call->ended_at = $call->ended_at?->copy()->addDays($days);
            $call->conversation_date = $call->conversation_date?->copy()->addDays($days);
            $call->saveQuietly();
        }

        $demoCallIds = $demoCalls->pluck('id');

        ConversationAnalysis::query()
            ->where('organization_id', $organization->id)
            ->whereIn('call_id', $demoCallIds)
            ->each(function (ConversationAnalysis $analysis) use ($days): void {
                $analysis->analyzed_at = $analysis->analyzed_at?->copy()->addDays($days);
                $analysis->saveQuietly();
            });

        OrganizationActivity::query()
            ->where('organization_id', $organization->id)
            ->where('metadata->source', 'demo_seeder')
            ->each(function (OrganizationActivity $activity) use ($days): void {
                $activity->created_at = $activity->created_at?->copy()->addDays($days);
                $activity->updated_at = $activity->updated_at?->copy()->addDays($days);
                $activity->saveQuietly();
            });

        $this->syncCustomerContactWindows($organization);
        $this->forgetCachedDashboard($organization);

        return $days;
    }

    private function calendarDaysUntilToday(Carbon $latest): int
    {
        $from = $latest->copy()->startOfDay();
        $to = now()->copy()->startOfDay();
        $interval = $from->diff($to);
        $days = (int) $interval->days;

        return $interval->invert === 1 ? -$days : $days;
    }

    private function syncCustomerContactWindows(Organization $organization): void
    {
        $windows = Call::query()
            ->where('organization_id', $organization->id)
            ->whereNotNull('customer_id')
            ->selectRaw('customer_id, MIN(started_at) as first_at, MAX(started_at) as last_at')
            ->groupBy('customer_id')
            ->get();

        foreach ($windows as $window) {
            Customer::query()
                ->where('organization_id', $organization->id)
                ->whereKey($window->customer_id)
                ->update([
                    'first_contact_at' => $window->first_at,
                    'last_contact_at' => $window->last_at,
                ]);
        }

        CustomerCompany::query()
            ->where('organization_id', $organization->id)
            ->get()
            ->each(fn (CustomerCompany $company) => $this->customerCompanies->refreshAggregates($company));
    }

    private function forgetCachedDashboard(Organization $organization): void
    {
        Cache::forget('performance:team:'.ReportFilter::make($organization->id, ReportDatePreset::Last30)->cacheKey());

        $sinceKey = blank(config('dashboard.insight_lists_since'))
            ? 'none'
            : (string) \Carbon\Carbon::parse((string) config('dashboard.insight_lists_since'))->getTimestamp();

        foreach ([30, 90] as $days) {
            Cache::forget("dashboard:forgotten:{$organization->id}:{$days}");
            Cache::forget("dashboard:forgotten:{$organization->id}:{$days}:{$sinceKey}");
        }
    }
}
