<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        IdempotentSchema::create('ai_usage_daily_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_user_id')->nullable()->constrained('organization_user')->cascadeOnDelete();
            $table->date('period_date');
            $table->unsignedInteger('analyses_count')->default(0);
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('total_tokens')->default(0);
            $table->decimal('total_cost', 14, 6)->default(0);
            $table->unsignedInteger('total_processing_duration_ms')->default(0);
            $table->decimal('average_score', 5, 2)->nullable();
            $table->string('llm_provider')->nullable();
            $table->string('model_name')->nullable();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'organization_user_id', 'period_date'],
                'ai_usage_daily_unique',
            );
            $table->index(['organization_id', 'period_date']);
            $table->index(['organization_user_id', 'period_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_daily_snapshots');
    }
};
