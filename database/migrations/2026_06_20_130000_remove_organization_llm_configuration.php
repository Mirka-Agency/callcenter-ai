<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('conversation_analyses', 'organization_llm_connection_id')) {
            Schema::table('conversation_analyses', function (Blueprint $table) {
                $table->dropConstrainedForeignId('organization_llm_connection_id');
            });
        }

        if (Schema::hasColumn('llm_analysis_logs', 'organization_llm_connection_id')) {
            Schema::table('llm_analysis_logs', function (Blueprint $table) {
                $table->dropConstrainedForeignId('organization_llm_connection_id');
            });
        }

        Schema::dropIfExists('organization_llm_connections');
        Schema::dropIfExists('organization_ai_settings');
    }

    public function down(): void
    {
        IdempotentSchema::create('organization_llm_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('llm_provider_id')->constrained('llm_providers')->cascadeOnDelete();
            $table->string('name');
            $table->json('credentials')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });

        IdempotentSchema::create('organization_ai_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('llm_provider_id')->nullable()->constrained('llm_providers')->nullOnDelete();
            $table->foreignId('llm_model_id')->nullable()->constrained('llm_models')->nullOnDelete();
            $table->string('custom_prompt_version')->nullable();
            $table->timestamps();
        });

        Schema::table('conversation_analyses', function (Blueprint $table) {
            if (! Schema::hasColumn('conversation_analyses', 'organization_llm_connection_id')) {
                $table->foreignId('organization_llm_connection_id')
                    ->nullable()
                    ->after('organization_id')
                    ->constrained('organization_llm_connections')
                    ->nullOnDelete();
            }
        });

        Schema::table('llm_analysis_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('llm_analysis_logs', 'organization_llm_connection_id')) {
                $table->foreignId('organization_llm_connection_id')
                    ->nullable()
                    ->constrained('organization_llm_connections')
                    ->cascadeOnDelete();
            }
        });
    }
};
