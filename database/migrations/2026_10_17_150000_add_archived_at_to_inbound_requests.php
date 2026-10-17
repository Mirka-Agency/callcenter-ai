<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['consultation_requests', 'demo_requests'] as $table) {
            IdempotentSchema::tableIfMissingColumns($table, ['archived_at'], function (Blueprint $blueprint) {
                $blueprint->timestamp('archived_at')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['consultation_requests', 'demo_requests'] as $table) {
            if (Schema::hasColumn($table, 'archived_at')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('archived_at');
                });
            }
        }
    }
};
