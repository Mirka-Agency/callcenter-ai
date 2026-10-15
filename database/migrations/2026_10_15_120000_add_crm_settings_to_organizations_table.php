<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        IdempotentSchema::tableIfMissingColumns('organizations', ['crm_settings'], function (Blueprint $table): void {
            if (! Schema::hasColumn('organizations', 'crm_settings')) {
                $table->json('crm_settings')->nullable()->after('call_intake_filters');
            }
        });
    }

    public function down(): void
    {
        IdempotentSchema::dropColumnsIfExist('organizations', 'crm_settings');
    }
};
