<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        IdempotentSchema::create('customer_identity_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('call_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_analysis_id')->nullable()->constrained('conversation_analyses')->nullOnDelete();
            $table->string('kind');
            $table->string('field')->default('');
            $table->text('current_value')->nullable();
            $table->text('suggested_value')->nullable();
            $table->string('evidence', 240)->nullable();
            $table->string('status')->default('pending');
            $table->json('context')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['conversation_analysis_id', 'kind', 'field'],
                'identity_reviews_analysis_kind_field_unique',
            );
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_identity_reviews');
    }
};
