<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_wallets', function (Blueprint $table) {
            if (! Schema::hasColumn('organization_wallets', 'low_balance_threshold')) {
                $table->decimal('low_balance_threshold', 14, 6)->nullable()->after('currency');
            }
        });
    }

    public function down(): void
    {
        IdempotentSchema::dropColumnsIfExist('organization_wallets', 'low_balance_threshold');
    }
};
