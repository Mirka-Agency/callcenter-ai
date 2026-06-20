<?php

namespace App\Services\Demo;

use App\Domain\Billing\Enums\WalletTransactionType;
use App\Enums\UserRole;
use App\Jobs\ProvisionDemoOrganizationJob;
use App\Models\Organization;
use App\Models\OrganizationWallet;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\Seeding\DemoAvatarAssigner;
use App\Support\Seeding\DemoCatalog;
use Illuminate\Support\Facades\Hash;

class DemoPersonProvisioner
{
    /** Create a demo org from raw CSV/form data, returning the organization. */
    public function provision(
        string $phone,
        string $name,
        string $email,
        string $password,
    ): Organization {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'phone' => $phone,
                'password' => Hash::make($password),
                'role' => UserRole::Employer,
                'email_verified_at' => now(),
                'avatar_path' => (new DemoAvatarAssigner)->assign('male'),
            ],
        );

        return $this->provisionForUser($user);
    }

    /** Create a demo org for an existing User model. */
    public function provisionForUser(User $user): Organization
    {
        $organization = Organization::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'title' => "دمو — {$user->name}",
                'disabled' => false,
                'is_demo' => true,
            ],
        );

        $this->seedWallet($organization);
        $this->seedEmployees($organization);

        dispatch(new ProvisionDemoOrganizationJob($organization->id));

        return $organization;
    }

    private function seedWallet(Organization $organization): void
    {
        $wallet = OrganizationWallet::query()->firstOrCreate(
            ['organization_id' => $organization->id],
            [
                'balance' => DemoCatalog::WALLET_BALANCE_IRR,
                'currency' => 'IRR',
            ],
        );

        if ((float) $wallet->balance < DemoCatalog::WALLET_BALANCE_IRR) {
            $wallet->update(['balance' => DemoCatalog::WALLET_BALANCE_IRR, 'currency' => 'IRR']);
        }

        WalletTransaction::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'type' => WalletTransactionType::Deposit,
                'description' => 'شارژ اولیه دمو (۲۰٬۰۰۰ تومان)',
            ],
            [
                'amount' => DemoCatalog::WALLET_BALANCE_IRR,
                'balance_before' => 0,
                'balance_after' => DemoCatalog::WALLET_BALANCE_IRR,
                'created_at' => now()->subDays(30),
            ],
        );
    }

    private function seedEmployees(Organization $organization): void
    {
        $provisioner = app(DemoEmployeeProvisioner::class);
        $avatarAssigner = new DemoAvatarAssigner;

        for ($i = 1; $i <= DemoCatalog::EMPLOYEES_PER_ORGANIZATION; $i++) {
            $provisioner->provision($organization, $i, 0, $avatarAssigner);
        }
    }
}
