<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_ai_settings', function (Blueprint $table) {
            $table->string('billing_unit_currency', 3)->default('USD')->after('currency');
            $table->decimal('billing_unit_price', 16, 2)->default(500_000)->after('billing_unit_currency');
        });
    }

    public function down(): void
    {
        Schema::table('platform_ai_settings', function (Blueprint $table) {
            $table->dropColumn(['billing_unit_currency', 'billing_unit_price']);
        });
    }
};
