<?php

namespace App\Livewire\Employer\Wallet;

use App\Domain\AiUsage\Enums\UsageAggregationPeriod;
use App\Models\OrganizationWallet;
use App\Models\WalletTransaction;
use App\Services\AiBillingService;
use App\Services\AiUsageAnalyticsService;
use App\Services\EmployerContext;
use App\Services\WalletService;
use App\Support\AiInfrastructure;
use App\Support\OnPrem;
use App\Support\PersianNumber;
use Illuminate\Support\Number;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employer')]
#[Title('اعتبار هوش مصنوعی')]
class Index extends Component
{
    public bool $editingThreshold = false;

    public string $thresholdInput = '';

    public function mount(): void
    {
        abort_if(OnPrem::billingHidden(), 404);
    }

    public function startEditingThreshold(): void
    {
        $wallet = $this->wallet();
        $this->thresholdInput = $this->formatThresholdInput($wallet);
        $this->resetValidation();
        $this->editingThreshold = true;
    }

    public function cancelEditingThreshold(): void
    {
        $this->editingThreshold = false;
        $this->thresholdInput = '';
        $this->resetValidation();
    }

    public function saveThreshold(): void
    {
        $wallet = $this->wallet();
        $parsed = PersianNumber::parse($this->thresholdInput);
        $this->thresholdInput = is_numeric($parsed) ? (string) $parsed : trim($this->thresholdInput);

        $min = $wallet->currency === 'IRR' ? 1 : 0.01;

        $this->validate([
            'thresholdInput' => ['required', 'numeric', 'min:'.$min, 'max:99999999'],
        ]);

        $wallet->update([
            'low_balance_threshold' => round((float) $this->thresholdInput, 6),
        ]);

        $this->editingThreshold = false;
        $this->thresholdInput = '';

        $detail = json_encode(
            ['type' => 'success', 'message' => __('ui.wallet.threshold_saved')],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP,
        );

        $this->js("window.dispatchEvent(new CustomEvent('show-toast', { detail: {$detail} }))");
    }

    public function render()
    {
        $organizationId = EmployerContext::organizationId();
        $billing = app(AiBillingService::class);
        $analytics = app(AiUsageAnalyticsService::class);
        $wallet = $this->wallet();

        $overview = $billing->walletOverview($organizationId);
        $dailyTrend = $analytics->organizationTrend($organizationId, UsageAggregationPeriod::Daily, 30);
        $monthlyTrend = $analytics->organizationTrend($organizationId, UsageAggregationPeriod::Monthly, 180);
        $monthOverview = $analytics->organizationOverview(
            $organizationId,
            now()->startOfMonth(),
            now()->endOfMonth(),
        );

        $lowBalanceThreshold = $wallet->lowBalanceThreshold();
        $lowBalance = $wallet->hasLowBalance();
        $criticalBalance = $wallet->hasCriticalBalance();

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
            'showAiInfrastructure' => AiInfrastructure::isVisible(),
            'formatMoney' => fn (float|int $amount) => Number::currency(
                $amount,
                $overview['currency'],
                'fa',
            ),
        ]);
    }

    private function wallet(): OrganizationWallet
    {
        return app(WalletService::class)->forOrganization(EmployerContext::organizationId());
    }

    private function formatThresholdInput(OrganizationWallet $wallet): string
    {
        $threshold = $wallet->lowBalanceThreshold();

        if ($wallet->currency === 'IRR') {
            return PersianNumber::format((int) round($threshold), 0) ?? (string) (int) round($threshold);
        }

        return PersianNumber::format($threshold, null, 6) ?? (string) $threshold;
    }
}
