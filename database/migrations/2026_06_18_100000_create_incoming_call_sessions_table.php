<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        IdempotentSchema::create('incoming_call_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_voip_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('voip_call_log_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('call_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_call_id')->nullable();
            $table->string('caller_number');
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('direction')->default('inbound');
            $table->string('status')->default('ringing');
            $table->foreignId('claimed_by_organization_user_id')->nullable()->constrained('organization_user')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->json('customer_context_json')->nullable();
            $table->json('recommended_actions_json')->nullable();
            $table->json('recent_actions_json')->nullable();
            $table->json('customer_timeline_json')->nullable();
            $table->timestamp('ring_started_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['external_call_id', 'organization_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incoming_call_sessions');
    }
};
