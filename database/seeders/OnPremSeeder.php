<?php

namespace Database\Seeders;

use App\Domain\Billing\Enums\WalletTransactionType;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\OrganizationWallet;
use App\Models\PlatformAiSettings;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Bootstrap a single-employer on-prem install (no demo multi-org data).
 *
 * @see docs/on-prem.md
 */
class OnPremSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PlatformFoundationSeeder::class);

        $adminEmail = $this->nonEmptyString(config('onprem.admin_email'), 'onprem.admin_email');
        $adminPassword = $this->nonEmptyString(config('onprem.admin_password'), 'onprem.admin_password');
        $employerEmail = $this->nonEmptyString(config('onprem.employer_email'), 'onprem.employer_email');
        $employerPassword = $this->nonEmptyString(config('onprem.employer_password'), 'onprem.employer_password');

        if ($adminEmail === $employerEmail) {
            throw new RuntimeException('ONPREM_ADMIN_EMAIL and ONPREM_EMPLOYER_EMAIL must differ.');
        }

        User::query()->updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => (string) config('onprem.admin_name', 'Super Admin'),
                'password' => Hash::make($adminPassword),
                'role' => UserRole::SuperAdmin,
                'email_verified_at' => now(),
            ],
        );

        $employer = User::query()->updateOrCreate(
            ['email' => $employerEmail],
            [
                'name' => (string) config('onprem.employer_name', 'مدیر سازمان'),
                'password' => Hash::make($employerPassword),
                'role' => UserRole::Employer,
                'email_verified_at' => now(),
            ],
        );

        $organization = Organization::query()->updateOrCreate(
            ['user_id' => $employer->id],
            [
                'title' => (string) config('onprem.org_title', 'سازمان محلی'),
                'disabled' => false,
                'is_demo' => false,
                'employer_can_manage_integrations' => (bool) config('onprem.employer_can_manage_integrations', true),
            ],
        );

        $this->seedWallet($organization);

        $this->command?->info('On-prem bootstrap complete.');
        $this->command?->info("Admin:    {$adminEmail} → /admin");
        $this->command?->info("Employer: {$employerEmail} → /app");
        $this->command?->warn('Change default passwords if you used example values.');
    }

    private function seedWallet(Organization $organization): void
    {
        $currency = PlatformAiSettings::currencyCode();
        $balance = (float) config('onprem.wallet_balance', 100_000_000);

        $wallet = OrganizationWallet::query()->firstOrCreate(
            ['organization_id' => $organization->id],
            [
                'balance' => $balance,
                'currency' => $currency,
            ],
        );

        if ((float) $wallet->balance < $balance) {
            $wallet->update([
                'balance' => $balance,
                'currency' => $currency,
            ]);
        }

        WalletTransaction::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'type' => WalletTransactionType::Deposit,
                'description' => 'شارژ اولیه استقرار محلی',
            ],
            [
                'amount' => $balance,
                'balance_before' => 0,
                'balance_after' => $balance,
                'created_at' => now(),
            ],
        );
    }

    private function nonEmptyString(mixed $value, string $configKey): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException("Missing required config: {$configKey}");
        }

        return trim($value);
    }
}
