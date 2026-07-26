<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            if (! Schema::hasColumn('calls', 'processing_status')) {
                $table->string('processing_status')->default('pending')->after('status');
            }
            if (! Schema::hasColumn('calls', 'processing_error')) {
                $table->text('processing_error')->nullable()->after('processing_status');
            }
        });

        Schema::table('conversation_analyses', function (Blueprint $table) {
            if (! Schema::hasColumn('conversation_analyses', 'transcript')) {
                $table->longText('transcript')->nullable()->after('summary');
            }
            if (Schema::hasColumn('conversation_analyses', 'call_transcript_id')) {
                $table->unsignedBigInteger('call_transcript_id')->nullable()->change();
            }
        });

        if (Schema::hasColumn('crm_pipeline_syncs', 'pipeline_execution_id')) {
            IdempotentSchema::dropForeignKeyIfExists('crm_pipeline_syncs', 'pipeline_execution_id');

            Schema::table('crm_pipeline_syncs', function (Blueprint $table) {
                $table->unsignedBigInteger('pipeline_execution_id')->nullable()->change();
            });

            if (! IdempotentSchema::hasForeignKey('crm_pipeline_syncs', 'pipeline_execution_id')) {
                Schema::table('crm_pipeline_syncs', function (Blueprint $table) {
                    $table->foreign('pipeline_execution_id')
                        ->references('id')
                        ->on('pipeline_executions')
                        ->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('crm_pipeline_syncs', 'pipeline_execution_id')) {
            IdempotentSchema::dropForeignKeyIfExists('crm_pipeline_syncs', 'pipeline_execution_id');

            Schema::table('crm_pipeline_syncs', function (Blueprint $table) {
                $table->unsignedBigInteger('pipeline_execution_id')->nullable(false)->change();
            });

            if (! IdempotentSchema::hasForeignKey('crm_pipeline_syncs', 'pipeline_execution_id')) {
                Schema::table('crm_pipeline_syncs', function (Blueprint $table) {
                    $table->foreign('pipeline_execution_id')
                        ->references('id')
                        ->on('pipeline_executions')
                        ->cascadeOnDelete();
                });
            }
        }

        IdempotentSchema::dropColumnsIfExist('conversation_analyses', 'transcript');
        IdempotentSchema::dropColumnsIfExist('calls', 'processing_status', 'processing_error');
    }
};
