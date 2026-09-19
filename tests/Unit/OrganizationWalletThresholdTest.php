<?php

namespace Tests\Unit;

use App\Models\Organization;
use App\Models\OrganizationWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationWalletThresholdTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_low_balance_threshold_is_used_when_unset(): void
    {
        $wallet = $this->wallet(['currency' => 'IRR', 'balance' => 200_000]);

        $this->assertNull($wallet->low_balance_threshold);
        $this->assertSame(100_000.0, $wallet->lowBalanceThreshold());
        $this->assertFalse($wallet->hasLowBalance());
        $this->assertSame(10_000.0, $wallet->criticalBalanceThreshold());
    }

    public function test_custom_low_balance_threshold_is_used_for_warnings(): void
    {
        $wallet = $this->wallet([
            'currency' => 'IRR',
            'balance' => 80_000,
            'low_balance_threshold' => 50_000,
        ]);

        $this->assertSame(50_000.0, $wallet->lowBalanceThreshold());
        $this->assertFalse($wallet->hasLowBalance());
        $this->assertSame(5_000.0, $wallet->criticalBalanceThreshold());

        $wallet->update(['low_balance_threshold' => 100_000]);

        $this->assertTrue($wallet->fresh()->hasLowBalance());
    }

    public function test_non_irr_wallets_use_the_smaller_default_threshold(): void
    {
        $wallet = $this->wallet(['currency' => 'USD', 'balance' => 5]);

        $this->assertSame(10.0, $wallet->lowBalanceThreshold());
        $this->assertTrue($wallet->hasLowBalance());
    }

    /** @param  array<string, mixed>  $overrides */
    private function wallet(array $overrides = []): OrganizationWallet
    {
        $organization = Organization::factory()->create();
        $wallet = $organization->wallet()->firstOrFail();

        $wallet->update($overrides);

        return $wallet->fresh();
    }
}
