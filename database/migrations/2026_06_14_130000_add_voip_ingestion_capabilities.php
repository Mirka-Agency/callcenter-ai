<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voip_providers', function (Blueprint $table) {
            if (! Schema::hasColumn('voip_providers', 'supports_webhook')) {
                $table->boolean('supports_webhook')->default(true)->after('adapter_class');
            }
            if (! Schema::hasColumn('voip_providers', 'supports_polling')) {
                $table->boolean('supports_polling')->default(false)->after('supports_webhook');
            }
            if (! Schema::hasColumn('voip_providers', 'polling_interval_seconds')) {
                $table->unsignedSmallInteger('polling_interval_seconds')->default(30)->after('supports_polling');
            }
        });

        Schema::table('organization_voip_connections', function (Blueprint $table) {
            if (! Schema::hasColumn('organization_voip_connections', 'ingestion_mode')) {
                $table->string('ingestion_mode')->default('webhook')->after('is_active');
            }
            if (! Schema::hasColumn('organization_voip_connections', 'polling_enabled')) {
                $table->boolean('polling_enabled')->default(false)->after('ingestion_mode');
            }
            if (! Schema::hasColumn('organization_voip_connections', 'polling_interval_seconds')) {
                $table->unsignedSmallInteger('polling_interval_seconds')->nullable()->after('polling_enabled');
            }
            if (! Schema::hasColumn('organization_voip_connections', 'last_polled_at')) {
                $table->timestamp('last_polled_at')->nullable()->after('polling_interval_seconds');
            }

            if (! Schema::hasIndex('organization_voip_connections', ['polling_enabled', 'last_polled_at'])) {
                $table->index(['polling_enabled', 'last_polled_at']);
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasIndex('organization_voip_connections', ['polling_enabled', 'last_polled_at'])) {
            Schema::table('organization_voip_connections', function (Blueprint $table) {
                $table->dropIndex(['polling_enabled', 'last_polled_at']);
            });
        }
        IdempotentSchema::dropColumnsIfExist(
            'organization_voip_connections',
            'ingestion_mode',
            'polling_enabled',
            'polling_interval_seconds',
            'last_polled_at',
        );

        IdempotentSchema::dropColumnsIfExist(
            'voip_providers',
            'supports_webhook',
            'supports_polling',
            'polling_interval_seconds',
        );
    }
};
