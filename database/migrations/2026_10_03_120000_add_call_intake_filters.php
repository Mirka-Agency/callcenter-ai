<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        IdempotentSchema::tableIfMissingColumns('organizations', ['call_intake_filters'], function (Blueprint $table): void {
            $table->json('call_intake_filters')->nullable()->after('holiday_weekdays');
        });

        IdempotentSchema::tableIfMissingColumns('calls', ['is_internal_agent_call'], function (Blueprint $table): void {
            $table->boolean('is_internal_agent_call')->default(false)->after('counts_for_extension_reports');
        });
    }

    public function down(): void
    {
        IdempotentSchema::dropColumnsIfExist('organizations', 'call_intake_filters');
        IdempotentSchema::dropColumnsIfExist('calls', 'is_internal_agent_call');
    }
};
