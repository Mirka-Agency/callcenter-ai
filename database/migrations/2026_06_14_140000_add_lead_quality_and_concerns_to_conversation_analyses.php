<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_analyses', function (Blueprint $table) {
            if (! Schema::hasColumn('conversation_analyses', 'lead_quality_json')) {
                $table->json('lead_quality_json')->nullable()->after('operational_insights_json');
            }
            if (! Schema::hasColumn('conversation_analyses', 'concerns_json')) {
                $table->json('concerns_json')->nullable()->after('lead_quality_json');
            }
        });
    }

    public function down(): void
    {
        IdempotentSchema::dropColumnsIfExist('conversation_analyses', 'lead_quality_json', 'concerns_json');
    }
};
