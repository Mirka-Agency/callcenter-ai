<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            if (! Schema::hasColumn('organizations', 'crm_mode')) {
                $table->string('crm_mode', 16)->default('internal')->after('employer_can_manage_integrations');
            }
        });

        if (Schema::hasTable('organization_crm_connections')) {
            DB::table('organizations')
                ->whereIn('id', function ($query) {
                    $query->select('organization_id')
                        ->from('organization_crm_connections')
                        ->where('is_active', true);
                })
                ->update(['crm_mode' => 'external']);
        }
    }

    public function down(): void
    {
        IdempotentSchema::dropColumnsIfExist('organizations', 'crm_mode');
    }
};
