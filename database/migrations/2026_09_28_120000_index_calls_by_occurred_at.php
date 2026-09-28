<?php

use App\Services\Reports\DefinedExtensionCallConstraint;
use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        IdempotentSchema::tableIfMissingColumns('calls', ['counts_for_extension_reports'], function (Blueprint $table): void {
            $table->boolean('counts_for_extension_reports')->default(false);
            $table->index(['organization_id', 'counts_for_extension_reports'], 'calls_org_ext_report_idx');
        });

        if (! IdempotentSchema::hasIndex('call_processing_jobs', 'cpj_call_id_id_idx') && Schema::hasTable('call_processing_jobs')) {
            Schema::table('call_processing_jobs', function (Blueprint $table): void {
                $table->index(['call_id', 'id'], 'cpj_call_id_id_idx');
            });
        }

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX IF NOT EXISTS calls_organization_occurred_idx ON calls (organization_id, (COALESCE(conversation_date, started_at, created_at)))');
        }

        if (Schema::hasColumn('calls', 'counts_for_extension_reports')) {
            app(DefinedExtensionCallConstraint::class)->refreshAll();
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS calls_organization_occurred_idx');
        }

        if (IdempotentSchema::hasIndex('call_processing_jobs', 'cpj_call_id_id_idx')) {
            Schema::table('call_processing_jobs', function (Blueprint $table): void {
                $table->dropIndex('cpj_call_id_id_idx');
            });
        }

        IdempotentSchema::dropColumnsIfExist('calls', 'counts_for_extension_reports');
    }
};
