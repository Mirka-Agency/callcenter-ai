<?php

namespace App\Livewire\Employer\Wallet;

use App\Domain\AiUsage\Enums\UsageAggregationPeriod;
use App\Models\WalletTransaction;
use App\Services\AiBillingService;
use App\Services\AiUsageAnalyticsService;
use App\Services\EmployerContext;
use App\Services\WalletService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employer')]
#[Title('اعتبار هوش مصنوعی')]
class Index extends Component
{
    public function render()
    {
        $organizationId = EmployerContext::organizationId();
        $billing = app(AiBillingService::class);
        $analytics = app(AiUsageAnalyticsService::class);
        $wallet = app(WalletService::class)->forOrganization($organizationId);

        $overview = $billing->walletOverview($organizationId);
        $dailyTrend = $analytics->organizationTrend($organizationId, UsageAggregationPeriod::Daily, 30);
        $monthlyTrend = $analytics->organizationTrend($organizationId, UsageAggregationPeriod::Monthly, 180);
        $monthOverview = $analytics->organizationOverview(
            $organizationId,
            now()->startOfMonth(),
            now()->endOfMonth(),
        );

        $lowBalanceThreshold = $wallet->currency === 'IRR' ? 100_000 : 10;
        $criticalBalanceThreshold = $wallet->currency === 'IRR' ? 10_000 : 1;
        $lowBalance = $wallet->balance < $lowBalanceThreshold;
        $criticalBalance = $wallet->balance < $criticalBalanceThreshold;

        $totalCost30d = (float) collect($dailyTrend)->sum('total_cost');
        $avgDailyCost = $totalCost30d / max(1, count($dailyTrend));
        $estimatedDaysRemaining = $avgDailyCost > 0
            ? (int) floor((float) $wallet->balance / $avgDailyCost)
            : null;

        $recentTransactions = WalletTransaction::query()
            ->where('organization_id', $organizationId)
            ->latest('created_at')
            ->limit(12)
            ->get();

        return view('livewire.employer.wallet.index', [
            'overview' => $overview,
            'monthOverview' => $monthOverview,
            'dailyTrend' => $dailyTrend,
            'monthlyTrend' => $monthlyTrend,
            'recentTransactions' => $recentTransactions,
            'lowBalance' => $lowBalance,
            'criticalBalance' => $criticalBalance,
            'lowBalanceThreshold' => $lowBalanceThreshold,
            'estimatedDaysRemaining' => $estimatedDaysRemaining,
            'avgDailyCost' => $avgDailyCost,
            'showAiInfrastructure' => \App\Support\AiInfrastructure::isVisible(),
            'formatMoney' => fn (float|int $amount) => \Illuminate\Support\Number::currency(
                $amount,
                $overview['currency'],
                'fa',
            ),
        ]);
    }
}
