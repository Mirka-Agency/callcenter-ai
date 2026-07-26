<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            if (! Schema::hasColumn('organizations', 'employer_can_manage_integrations')) {
                $table->boolean('employer_can_manage_integrations')
                    ->default(false)
                    ->after('disabled');
            }
        });
    }

    public function down(): void
    {
        IdempotentSchema::dropColumnsIfExist('organizations', 'employer_can_manage_integrations');
    }
};
