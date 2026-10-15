<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        IdempotentSchema::create('crm_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 32);
            $table->string('priority', 16)->default('normal');
            $table->string('source', 32);
            $table->unsignedBigInteger('assignee_organization_user_id')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('customer_company_id')->nullable()->constrained('customer_companies')->nullOnDelete();
            $table->foreignId('crm_deal_id')->nullable()->constrained('crm_deals')->nullOnDelete();
            $table->foreignId('call_id')->nullable()->constrained('calls')->nullOnDelete();
            $table->foreignId('conversation_analysis_id')->nullable()->constrained('conversation_analyses')->nullOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->string('due_source', 32)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('completion_outcome')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status', 'due_at'], 'crm_tasks_org_status_due_idx');
            $table->index(['organization_id', 'assignee_organization_user_id'], 'crm_tasks_org_assignee_idx');
            $table->index(['organization_id', 'customer_id'], 'crm_tasks_org_customer_idx');
            $table->index(['organization_id', 'crm_deal_id'], 'crm_tasks_org_deal_idx');
            $table->foreign('assignee_organization_user_id')
                ->references('id')
                ->on('organization_user')
                ->nullOnDelete();
        });

        IdempotentSchema::create('crm_task_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_task_id')->constrained('crm_tasks')->cascadeOnDelete();
            $table->string('type', 40);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 32);
            $table->json('previous_json')->nullable();
            $table->json('next_json')->nullable();
            $table->string('summary')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'crm_task_id'], 'crm_task_events_lookup_idx');
        });

        IdempotentSchema::create('crm_ai_task_suggestions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_analysis_id')->constrained('conversation_analyses')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('crm_deal_id')->nullable()->constrained('crm_deals')->nullOnDelete();
            $table->foreignId('call_id')->nullable()->constrained('calls')->nullOnDelete();
            $table->string('action_fingerprint', 64);
            $table->string('proposed_title');
            $table->text('proposed_description')->nullable();
            $table->timestamp('proposed_due_at')->nullable();
            $table->string('due_basis', 32)->nullable();
            $table->string('status', 32);
            $table->json('payload');
            $table->foreignId('applied_crm_task_id')->nullable()->constrained('crm_tasks')->nullOnDelete();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_analysis_id', 'action_fingerprint'], 'crm_ai_task_suggestions_analysis_fp_unique');
            $table->index(['organization_id', 'status'], 'crm_ai_task_suggestions_status_idx');
        });

        IdempotentSchema::create('crm_task_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_task_id')->constrained('crm_tasks')->cascadeOnDelete();
            $table->string('kind', 32);
            $table->string('idempotency_key', 120);
            $table->timestamp('due_at_snapshot')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamps();

            $table->unique(['crm_task_id', 'idempotency_key'], 'crm_task_reminders_task_key_unique');
            $table->index(['organization_id', 'status', 'sent_at'], 'crm_task_reminders_org_status_idx');
        });

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_task_reminders');
        Schema::dropIfExists('crm_ai_task_suggestions');
        Schema::dropIfExists('crm_task_events');
        Schema::dropIfExists('crm_tasks');
    }
};
