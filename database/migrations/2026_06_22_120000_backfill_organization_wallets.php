<?php

use App\Models\Organization;
use App\Services\WalletService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $walletService = app(WalletService::class);

        Organization::query()
            ->whereDoesntHave('wallet')
            ->pluck('id')
            ->each(fn (int $organizationId) => $walletService->forOrganization($organizationId));
    }

    public function down(): void
    {
        // No rollback — wallets may already have transactions.
    }
};
