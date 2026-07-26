<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        IdempotentSchema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('normalized_phone');
            $table->string('phone_number')->nullable();
            $table->string('name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('email')->nullable();
            $table->string('job_title')->nullable();
            $table->decimal('identity_confidence', 4, 2)->default(0);
            $table->unsignedTinyInteger('latest_lead_score')->nullable();
            $table->string('latest_lead_level')->nullable();
            $table->json('common_concerns_json')->nullable();
            $table->string('purchase_intent')->nullable();
            $table->string('conversation_trend')->nullable();
            $table->text('recommended_next_action')->nullable();
            $table->timestamp('first_contact_at')->nullable();
            $table->timestamp('last_contact_at')->nullable();
            $table->unsignedInteger('total_calls')->default(0);
            $table->unsignedInteger('total_answered_calls')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'normalized_phone']);
            $table->index(['organization_id', 'last_contact_at']);
        });

        Schema::table('calls', function (Blueprint $table) {
            if (! Schema::hasColumn('calls', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        IdempotentSchema::dropConstrainedForeignIdIfExists('calls', 'customer_id');

        Schema::dropIfExists('customers');
    }
};
