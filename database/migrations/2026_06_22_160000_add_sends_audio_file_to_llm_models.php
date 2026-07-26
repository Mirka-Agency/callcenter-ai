<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('llm_models', function (Blueprint $table) {
            if (! Schema::hasColumn('llm_models', 'sends_audio_file')) {
                $table->boolean('sends_audio_file')->default(true)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        IdempotentSchema::dropColumnsIfExist('llm_models', 'sends_audio_file');
    }
};
