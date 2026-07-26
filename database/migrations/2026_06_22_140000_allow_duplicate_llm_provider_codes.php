<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasIndex('llm_providers', 'llm_providers_code_unique')) {
            Schema::table('llm_providers', function (Blueprint $table) {
                $table->dropUnique(['code']);
            });
        }

        IdempotentSchema::tableIfMissingIndex('llm_providers', 'llm_providers_code_index', function (Blueprint $table) {
            $table->index('code');
        });
    }

    public function down(): void
    {
        if (Schema::hasIndex('llm_providers', 'llm_providers_code_index')) {
            Schema::table('llm_providers', function (Blueprint $table) {
                $table->dropIndex(['code']);
            });
        }

        IdempotentSchema::tableIfMissingIndex('llm_providers', 'llm_providers_code_unique', function (Blueprint $table) {
            $table->unique('code');
        });
    }
};
