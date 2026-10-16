<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_pipeline_stages', function (Blueprint $table): void {
            if (! Schema::hasColumn('crm_pipeline_stages', 'stale_after_days')) {
                $table->unsignedSmallInteger('stale_after_days')->nullable()->after('is_active');
            }
        });

        Schema::table('crm_deals', function (Blueprint $table): void {
            if (! Schema::hasColumn('crm_deals', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('lost_reason');
            }
            if (! Schema::hasColumn('crm_deals', 'stage_entered_at')) {
                $table->timestamp('stage_entered_at')->nullable()->after('version');
            }
            if (! Schema::hasColumn('crm_deals', 'won_reason')) {
                $table->string('won_reason')->nullable()->after('stage_entered_at');
            }
            if (! Schema::hasColumn('crm_deals', 'lost_reason_code')) {
                $table->string('lost_reason_code', 64)->nullable()->after('won_reason');
            }
            if (! Schema::hasColumn('crm_deals', 'health_score')) {
                $table->unsignedTinyInteger('health_score')->nullable()->after('lost_reason_code');
            }
            if (! Schema::hasColumn('crm_deals', 'health_level')) {
                $table->string('health_level', 32)->nullable()->after('health_score');
            }
            if (! Schema::hasColumn('crm_deals', 'health_factors_json')) {
                $table->json('health_factors_json')->nullable()->after('health_level');
            }
            if (! Schema::hasColumn('crm_deals', 'health_calculated_at')) {
                $table->timestamp('health_calculated_at')->nullable()->after('health_factors_json');
            }
            if (! Schema::hasColumn('crm_deals', 'next_action_text')) {
                $table->string('next_action_text')->nullable()->after('health_calculated_at');
            }
            if (! Schema::hasColumn('crm_deals', 'next_action_due_on')) {
                $table->date('next_action_due_on')->nullable()->after('next_action_text');
            }
        });

        IdempotentSchema::create('crm_deal_close_reasons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('code', 64);
            $table->string('label');
            $table->unsignedSmallInteger('position')->default(1);
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_note')->default(false);
            $table->timestamps();

            $table->unique(['organization_id', 'kind', 'code'], 'crm_deal_close_reasons_org_kind_code_unique');
            $table->index(['organization_id', 'kind', 'is_active'], 'crm_deal_close_reasons_org_kind_active_idx');
        });

        IdempotentSchema::create('crm_deal_barriers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_deal_id')->constrained('crm_deals')->cascadeOnDelete();
            $table->string('category', 64);
            $table->string('label');
            $table->string('status', 32)->default('suggested');
            $table->unsignedInteger('occurrence_count')->default(1);
            $table->text('evidence_text')->nullable();
            $table->string('suggested_action')->nullable();
            $table->foreignId('source_call_id')->nullable()->constrained('calls')->nullOnDelete();
            $table->foreignId('source_conversation_analysis_id')->nullable()->constrained('conversation_analyses')->nullOnDelete();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['crm_deal_id', 'category', 'label'], 'crm_deal_barriers_deal_category_label_unique');
            $table->index(['organization_id', 'crm_deal_id', 'status'], 'crm_deal_barriers_lookup_idx');
        });

        if (Schema::hasTable('crm_deals') && Schema::hasColumn('crm_deals', 'stage_entered_at')) {
            DB::table('crm_deals')
                ->whereNull('stage_entered_at')
                ->orderBy('id')
                ->chunkById(200, function ($rows): void {
                    foreach ($rows as $row) {
                        $entered = DB::table('crm_deal_stage_history')
                            ->where('crm_deal_id', $row->id)
                            ->where('to_stage_id', $row->crm_pipeline_stage_id)
                            ->orderByDesc('changed_at')
                            ->value('changed_at');

                        DB::table('crm_deals')
                            ->where('id', $row->id)
                            ->update([
                                'stage_entered_at' => $entered ?: ($row->created_at ?? now()),
                            ]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_deal_barriers');
        Schema::dropIfExists('crm_deal_close_reasons');

        IdempotentSchema::dropColumnsIfExist('crm_deals', [
            'version',
            'stage_entered_at',
            'won_reason',
            'lost_reason_code',
            'health_score',
            'health_level',
            'health_factors_json',
            'health_calculated_at',
            'next_action_text',
            'next_action_due_on',
        ]);
        IdempotentSchema::dropColumnsIfExist('crm_pipeline_stages', ['stale_after_days']);
    }
};
