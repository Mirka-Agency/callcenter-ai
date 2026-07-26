<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        IdempotentSchema::create('call_processing_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('job_uuid')->unique();
            $table->foreignId('call_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_user_id')->nullable()->constrained('organization_user')->nullOnDelete();
            $table->foreignId('uploader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_name');
            $table->string('status')->default('queued');
            $table->string('stage')->default('uploaded');
            $table->unsignedTinyInteger('progress_percentage')->default(0);
            $table->timestamp('upload_started_at')->nullable();
            $table->timestamp('upload_completed_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('waiting_seconds')->nullable();
            $table->unsignedInteger('processing_duration_seconds')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'created_at']);
            $table->index(['call_id']);
        });

        IdempotentSchema::create('call_processing_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_processing_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('call_id')->constrained()->cascadeOnDelete();
            $table->string('level')->default('info');
            $table->string('source');
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['call_processing_job_id', 'created_at']);
            $table->index(['call_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_processing_logs');
        Schema::dropIfExists('call_processing_jobs');
    }
};
