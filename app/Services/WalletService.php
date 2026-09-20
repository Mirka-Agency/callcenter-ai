<?php

namespace App\Services;

use App\Domain\Billing\Enums\WalletTransactionType;
use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\ConversationAnalysis;
use App\Models\OrganizationWallet;
use App\Models\PlatformAiSettings;
use App\Models\WalletTransaction;
use App\Support\OnPrem;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function forOrganization(int $organizationId): OrganizationWallet
    {
        $currency = PlatformAiSettings::currencyCode();

        return OrganizationWallet::query()->firstOrCreate(
            ['organization_id' => $organizationId],
            ['balance' => 0, 'currency' => $currency],
        );
    }

    public function hasSufficientBalance(int $organizationId, float $requiredAmount): bool
    {
        if (OnPrem::billingHidden()) {
            return true;
        }

        $wallet = $this->forOrganization($organizationId);
        $settings = PlatformAiSettings::current();

        if ($settings->allow_negative_balance) {
            return true;
        }

        return (float) $wallet->balance >= $requiredAmount;
    }

    public function assertSufficientBalance(int $organizationId, float $minimumAmount = 0.01): void
    {
        if (! $this->hasSufficientBalance($organizationId, $minimumAmount)) {
            throw new InsufficientWalletBalanceException(
                'موجودی اعتبار تحلیل کافی نیست. برای ادامه، موجودی خود را شارژ کنید.',
            );
        }
    }

    public function deposit(int $organizationId, float $amount, ?string $description = null): WalletTransaction
    {
        return $this->applyTransaction(
            organizationId: $organizationId,
            type: WalletTransactionType::Deposit,
            amount: abs($amount),
            description: $description ?? 'واریز به کیف پول',
        );
    }

    public function withdraw(int $organizationId, float $amount, ?string $description = null): WalletTransaction
    {
        return $this->applyTransaction(
            organizationId: $organizationId,
            type: WalletTransactionType::Withdrawal,
            amount: -abs($amount),
            description: $description ?? 'برداشت از کیف پول',
        );
    }

    public function adjust(int $organizationId, float $amount, ?string $description = null): WalletTransaction
    {
        return $this->applyTransaction(
            organizationId: $organizationId,
            type: WalletTransactionType::Adjustment,
            amount: $amount,
            description: $description ?? 'تعدیل موجودی',
        );
    }

    public function chargeForAnalysis(ConversationAnalysis $analysis, float $cost): WalletTransaction
    {
        return $this->applyTransaction(
            organizationId: $analysis->organization_id,
            type: WalletTransactionType::AiUsage,
            amount: -abs($cost),
            description: "تحلیل هوش مصنوعی #{$analysis->id}",
            referenceType: ConversationAnalysis::class,
            referenceId: $analysis->id,
        );
    }

    private function applyTransaction(
        int $organizationId,
        WalletTransactionType $type,
        float $amount,
        ?string $description = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): WalletTransaction {
        return DB::transaction(function () use ($organizationId, $type, $amount, $description, $referenceType, $referenceId) {
            $wallet = OrganizationWallet::query()
                ->where('organization_id', $organizationId)
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                $wallet = $this->forOrganization($organizationId);
                $wallet = OrganizationWallet::query()
                    ->whereKey($wallet->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $balanceBefore = (float) $wallet->balance;
            $balanceAfter = round($balanceBefore + $amount, 6);

            if ($balanceAfter < 0 && ! PlatformAiSettings::current()->allow_negative_balance) {
                throw new InsufficientWalletBalanceException(__('ui.wallet.insufficient_transaction'));
            }

            $wallet->update(['balance' => $balanceAfter]);

            return WalletTransaction::query()->create([
                'organization_id' => $organizationId,
                'type' => $type,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
            ]);
        });
    }
}
