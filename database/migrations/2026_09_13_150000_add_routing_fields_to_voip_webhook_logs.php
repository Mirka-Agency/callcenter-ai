<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        IdempotentSchema::tableIfMissingColumns('voip_webhook_logs', [
            'resolved_extension',
            'organization_user_id',
        ], function (Blueprint $table): void {
            if (! Schema::hasColumn('voip_webhook_logs', 'resolved_extension')) {
                $table->string('resolved_extension')->nullable()->after('event_type');
            }

            if (! Schema::hasColumn('voip_webhook_logs', 'organization_user_id')) {
                $table->foreignId('organization_user_id')
                    ->nullable()
                    ->after('resolved_extension')
                    ->constrained('organization_user')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        IdempotentSchema::dropConstrainedForeignIdIfExists('voip_webhook_logs', 'organization_user_id');
        IdempotentSchema::dropColumnsIfExist('voip_webhook_logs', 'resolved_extension');
    }
};
