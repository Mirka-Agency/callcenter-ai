<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        IdempotentSchema::create('crm_pipelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'is_default'], 'crm_pipelines_org_default_idx');
            $table->index(['organization_id', 'is_active'], 'crm_pipelines_org_active_idx');
        });

        IdempotentSchema::create('crm_pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_pipeline_id')->constrained('crm_pipelines')->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 7);
            $table->unsignedInteger('position');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['crm_pipeline_id', 'position'], 'crm_pipeline_stages_position_unique');
            $table->index(['organization_id', 'crm_pipeline_id'], 'crm_pipeline_stages_org_pipeline_idx');
        });

        IdempotentSchema::create('crm_deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_pipeline_id')->constrained('crm_pipelines')->restrictOnDelete();
            $table->foreignId('crm_pipeline_stage_id')->constrained('crm_pipeline_stages')->restrictOnDelete();
            $table->foreignId('customer_company_id')->nullable()->constrained('customer_companies')->nullOnDelete();
            $table->unsignedBigInteger('owner_organization_user_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('amount_rials')->nullable();
            $table->char('currency', 3)->default('IRR');
            $table->string('status', 16);
            $table->string('source', 32);
            $table->date('expected_close_on')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('lost_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status', 'crm_pipeline_id'], 'crm_deals_org_status_pipeline_idx');
            $table->index(['organization_id', 'owner_organization_user_id'], 'crm_deals_org_owner_idx');
            $table->index(['organization_id', 'customer_company_id'], 'crm_deals_org_company_idx');
            $table->index(['organization_id', 'last_activity_at'], 'crm_deals_org_activity_idx');

            $table->foreign('owner_organization_user_id')
                ->references('id')
                ->on('organization_user')
                ->nullOnDelete();
        });

        IdempotentSchema::create('crm_deal_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_deal_id')->constrained('crm_deals')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['crm_deal_id', 'customer_id'], 'crm_deal_contacts_deal_customer_unique');
            $table->index(['organization_id', 'customer_id'], 'crm_deal_contacts_org_customer_idx');
        });

        IdempotentSchema::create('crm_deal_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_deal_id')->constrained('crm_deals')->cascadeOnDelete();
            $table->foreignId('call_id')->constrained('calls')->cascadeOnDelete();
            $table->foreignId('conversation_analysis_id')->nullable()->constrained('conversation_analyses')->nullOnDelete();
            $table->string('source', 32);
            $table->json('evidence_json')->nullable();
            $table->foreignId('linked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('call_id', 'crm_deal_calls_call_unique');
            $table->unique('conversation_analysis_id', 'crm_deal_calls_analysis_unique');
        });

        IdempotentSchema::create('crm_deal_stage_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_deal_id')->constrained('crm_deals')->cascadeOnDelete();
            $table->unsignedBigInteger('from_stage_id')->nullable();
            $table->unsignedBigInteger('to_stage_id')->nullable();
            $table->string('from_stage_name')->nullable();
            $table->string('to_stage_name')->nullable();
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 32);
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['organization_id', 'crm_deal_id'], 'crm_deal_stage_history_lookup_idx');

            $table->foreign('from_stage_id')->references('id')->on('crm_pipeline_stages')->nullOnDelete();
            $table->foreign('to_stage_id')->references('id')->on('crm_pipeline_stages')->nullOnDelete();
        });

        IdempotentSchema::create('crm_deal_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_deal_id')->constrained('crm_deals')->cascadeOnDelete();
            $table->string('type', 40);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 32);
            $table->json('previous_json')->nullable();
            $table->json('next_json')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'crm_deal_id'], 'crm_deal_activities_lookup_idx');
        });

        IdempotentSchema::create('crm_deal_ai_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_analysis_id')->constrained('conversation_analyses')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('call_id')->nullable()->constrained('calls')->nullOnDelete();
            $table->string('suggestion_type', 32);
            $table->string('status', 32);
            $table->string('proposed_title')->nullable();
            $table->json('payload');
            $table->foreignId('applied_crm_deal_id')->nullable()->constrained('crm_deals')->nullOnDelete();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique('conversation_analysis_id', 'crm_deal_ai_suggestions_analysis_unique');
            $table->index(['organization_id', 'status'], 'crm_deal_ai_suggestions_status_idx');
        });

        IdempotentSchema::create('crm_deal_external_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_deal_id')->constrained('crm_deals')->cascadeOnDelete();
            $table->foreignId('organization_crm_connection_id')->constrained('organization_crm_connections')->cascadeOnDelete();
            $table->string('external_id');
            $table->string('external_pipeline_id')->nullable();
            $table->string('external_stage_id')->nullable();
            $table->string('sync_status', 32)->default('idle');
            $table->text('last_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['crm_deal_id', 'organization_crm_connection_id'], 'crm_deal_external_deal_connection_unique');
            $table->unique(['organization_crm_connection_id', 'external_id'], 'crm_deal_external_connection_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_deal_external_links');
        Schema::dropIfExists('crm_deal_ai_suggestions');
        Schema::dropIfExists('crm_deal_activities');
        Schema::dropIfExists('crm_deal_stage_history');
        Schema::dropIfExists('crm_deal_calls');
        Schema::dropIfExists('crm_deal_contacts');
        Schema::dropIfExists('crm_deals');
        Schema::dropIfExists('crm_pipeline_stages');
        Schema::dropIfExists('crm_pipelines');
    }
};
