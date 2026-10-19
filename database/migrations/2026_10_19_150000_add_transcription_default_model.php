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
            if (! Schema::hasColumn('llm_models', 'is_transcription_default')) {
                $table->boolean('is_transcription_default')->default(false)->after('is_default');
            }
        });

        Schema::table('platform_ai_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_ai_settings', 'default_transcription_provider_id')) {
                $table->foreignId('default_transcription_provider_id')
                    ->nullable()
                    ->after('default_llm_model_id')
                    ->constrained('llm_providers')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('platform_ai_settings', 'default_transcription_model_id')) {
                $table->foreignId('default_transcription_model_id')
                    ->nullable()
                    ->after('default_transcription_provider_id')
                    ->constrained('llm_models')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        IdempotentSchema::dropConstrainedForeignIdIfExists('platform_ai_settings', 'default_transcription_model_id');
        IdempotentSchema::dropConstrainedForeignIdIfExists('platform_ai_settings', 'default_transcription_provider_id');
        IdempotentSchema::dropColumnsIfExist('llm_models', 'is_transcription_default');
    }
};
