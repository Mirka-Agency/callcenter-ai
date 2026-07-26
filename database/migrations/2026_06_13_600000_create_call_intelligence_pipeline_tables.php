<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        IdempotentSchema::create('transcription_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();
        });

        IdempotentSchema::create('organization_transcription_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            // Short FK name: MySQL limits identifiers to 64 chars (default Laravel name is 72).
            $table->foreignId('transcription_provider_id');
            $table->foreign('transcription_provider_id', 'otc_transcription_provider_fk')
                ->references('id')
                ->on('transcription_providers')
                ->cascadeOnDelete();
            $table->string('name');
            $table->json('credentials');
            $table->json('settings')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'name'], 'otc_org_name_unique');
        });

        IdempotentSchema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_user_id')->nullable()->constrained('organization_user')->nullOnDelete();
            $table->foreignId('organization_voip_connection_id')->nullable()->constrained('organization_voip_connections')->nullOnDelete();
            $table->foreignId('organization_crm_connection_id')->nullable()->constrained('organization_crm_connections')->nullOnDelete();
            $table->foreignId('voip_call_log_id')->nullable()->constrained('voip_call_logs')->nullOnDelete();
            $table->string('provider_code');
            $table->string('external_call_id');
            $table->string('direction');
            $table->string('caller_number');
            $table->string('receiver_number');
            $table->string('status')->default('initiated');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'provider_code', 'external_call_id'], 'calls_org_provider_external_unique');
            $table->index(['organization_id', 'started_at']);
        });

        IdempotentSchema::create('call_recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained()->cascadeOnDelete();
            $table->string('source_url')->nullable();
            $table->string('storage_disk')->default('local');
            $table->string('storage_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('channels')->default('mono');
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamps();
        });

        IdempotentSchema::create('pipeline_executions', function (Blueprint $table) {
            $table->id();
            $table->uuid('pipeline_id')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('call_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_user_id')->nullable()->constrained('organization_user')->nullOnDelete();
            $table->string('current_stage')->default('call_received');
            $table->string('status')->default('running');
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        IdempotentSchema::create('pipeline_stage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_execution_id')->constrained()->cascadeOnDelete();
            $table->string('stage');
            $table->string('status');
            $table->text('message')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('call_transcripts', function (Blueprint $table) {
            if (! Schema::hasColumn('call_transcripts', 'call_id')) {
                $table->foreignId('call_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('call_transcripts', 'transcription_provider')) {
                $table->string('transcription_provider')->nullable()->after('language');
            }
            if (! Schema::hasColumn('call_transcripts', 'model_name')) {
                $table->string('model_name')->nullable()->after('transcription_provider');
            }
            if (! Schema::hasColumn('call_transcripts', 'speakers_json')) {
                $table->json('speakers_json')->nullable()->after('content');
            }
            if (! Schema::hasColumn('call_transcripts', 'confidence_scores_json')) {
                $table->json('confidence_scores_json')->nullable()->after('speakers_json');
            }
            if (! Schema::hasColumn('call_transcripts', 'processing_duration_ms')) {
                $table->unsignedInteger('processing_duration_ms')->nullable()->after('confidence_scores_json');
            }
        });

        Schema::table('conversation_analyses', function (Blueprint $table) {
            if (! Schema::hasColumn('conversation_analyses', 'call_id')) {
                $table->foreignId('call_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('conversation_analyses', 'pipeline_execution_id')) {
                $table->foreignId('pipeline_execution_id')->nullable()->after('call_transcript_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('conversation_analyses', 'performance_dimensions_json')) {
                $table->json('performance_dimensions_json')->nullable()->after('next_actions_json');
            }
            if (! Schema::hasColumn('conversation_analyses', 'customer_insights_json')) {
                $table->json('customer_insights_json')->nullable()->after('performance_dimensions_json');
            }
            if (! Schema::hasColumn('conversation_analyses', 'operational_insights_json')) {
                $table->json('operational_insights_json')->nullable()->after('customer_insights_json');
            }
        });

        IdempotentSchema::create('employee_performance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_user_id')->constrained('organization_user')->cascadeOnDelete();
            $table->string('period');
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedTinyInteger('average_score')->default(0);
            $table->unsignedSmallInteger('conversations_count')->default(0);
            $table->json('dimension_averages_json')->nullable();
            $table->json('top_strengths_json')->nullable();
            $table->json('top_weaknesses_json')->nullable();
            $table->timestamps();

            $table->unique(['organization_user_id', 'period', 'period_start'], 'employee_perf_period_unique');
        });

        IdempotentSchema::create('crm_pipeline_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pipeline_execution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_analysis_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_crm_connection_id')->constrained()->cascadeOnDelete();
            $table->string('provider_code');
            $table->string('action_type');
            $table->string('status');
            $table->string('external_id')->nullable();
            $table->json('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_pipeline_syncs');
        Schema::dropIfExists('employee_performance_snapshots');

        IdempotentSchema::dropConstrainedForeignIdIfExists('conversation_analyses', 'pipeline_execution_id');
        IdempotentSchema::dropConstrainedForeignIdIfExists('conversation_analyses', 'call_id');
        IdempotentSchema::dropColumnsIfExist(
            'conversation_analyses',
            'performance_dimensions_json',
            'customer_insights_json',
            'operational_insights_json',
        );

        IdempotentSchema::dropConstrainedForeignIdIfExists('call_transcripts', 'call_id');
        IdempotentSchema::dropColumnsIfExist(
            'call_transcripts',
            'transcription_provider',
            'model_name',
            'speakers_json',
            'confidence_scores_json',
            'processing_duration_ms',
        );

        Schema::dropIfExists('pipeline_stage_logs');
        Schema::dropIfExists('pipeline_executions');
        Schema::dropIfExists('call_recordings');
        Schema::dropIfExists('calls');
        Schema::dropIfExists('organization_transcription_connections');
        Schema::dropIfExists('transcription_providers');
    }
};
