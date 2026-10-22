<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Wallets were removed. This backfill is intentionally empty so fresh installs
        // do not depend on the deleted wallet service.
    }

    public function down(): void
    {
        // No rollback — wallets may already have transactions.
    }
};
