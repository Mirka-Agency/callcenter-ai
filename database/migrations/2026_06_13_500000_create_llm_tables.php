<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        IdempotentSchema::create('llm_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();
        });

        IdempotentSchema::create('organization_llm_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('llm_provider_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('credentials');
            $table->json('settings')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });

        IdempotentSchema::create('llm_prompt_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('llm_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('version')->unique();
            $table->string('name');
            $table->text('system_prompt');
            $table->text('user_prompt_template')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        IdempotentSchema::create('call_transcripts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voip_call_log_id')->nullable()->constrained('voip_call_logs')->nullOnDelete();
            $table->foreignId('organization_user_id')->nullable()->constrained('organization_user')->nullOnDelete();
            $table->foreignId('organization_voip_connection_id')->nullable()->constrained('organization_voip_connections')->nullOnDelete();
            $table->longText('content')->nullable();
            $table->string('language', 16)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        IdempotentSchema::create('conversation_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_user_id')->nullable()->constrained('organization_user')->nullOnDelete();
            $table->foreignId('voip_call_log_id')->nullable()->constrained('voip_call_logs')->nullOnDelete();
            $table->foreignId('call_transcript_id')->constrained('call_transcripts')->cascadeOnDelete();
            $table->foreignId('organization_llm_connection_id')->nullable()->constrained('organization_llm_connections')->nullOnDelete();
            $table->string('llm_provider');
            $table->string('model_name');
            $table->string('prompt_version')->nullable();
            $table->unsignedTinyInteger('score')->default(0);
            $table->text('summary');
            $table->string('sentiment');
            $table->text('overall_evaluation')->nullable();
            $table->json('strengths_json');
            $table->json('weaknesses_json');
            $table->json('next_actions_json');
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->decimal('cost', 12, 6)->default(0);
            $table->unsignedInteger('processing_duration_ms')->default(0);
            $table->timestamp('analyzed_at');
            $table->timestamps();

            $table->index(['organization_id', 'analyzed_at']);
            $table->index(['organization_user_id', 'analyzed_at']);
            $table->index(['organization_id', 'score']);
            $table->index(['organization_id', 'sentiment']);
        });

        IdempotentSchema::create('llm_analysis_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_llm_connection_id')->constrained()->cascadeOnDelete();
            $table->string('operation');
            $table->string('status');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('message')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->decimal('cost', 12, 6)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_analysis_logs');
        Schema::dropIfExists('conversation_analyses');
        Schema::dropIfExists('call_transcripts');
        Schema::dropIfExists('llm_prompt_versions');
        Schema::dropIfExists('organization_llm_connections');
        Schema::dropIfExists('llm_providers');
    }
};
