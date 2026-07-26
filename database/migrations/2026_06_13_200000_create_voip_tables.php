<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        IdempotentSchema::create('voip_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('adapter_class');
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();
        });

        IdempotentSchema::create('organization_voip_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voip_provider_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('credentials');
            $table->json('settings')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });

        IdempotentSchema::create('voip_call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_voip_connection_id')->constrained()->cascadeOnDelete();
            $table->string('provider_code');
            $table->string('external_call_id');
            $table->string('direction');
            $table->string('source_number');
            $table->string('destination_number');
            $table->string('status')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->string('recording_url')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['organization_voip_connection_id', 'external_call_id'], 'voip_call_logs_connection_call_unique');
        });

        IdempotentSchema::create('voip_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_voip_connection_id')->constrained()->cascadeOnDelete();
            $table->string('event_type')->nullable();
            $table->string('status');
            $table->json('payload')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();
        });

        IdempotentSchema::create('voip_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_voip_connection_id')->constrained()->cascadeOnDelete();
            $table->string('operation');
            $table->string('status');
            $table->json('payload')->nullable();
            $table->text('message')->nullable();
            $table->unsignedInteger('records_processed')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voip_sync_logs');
        Schema::dropIfExists('voip_webhook_logs');
        Schema::dropIfExists('voip_call_logs');
        Schema::dropIfExists('organization_voip_connections');
        Schema::dropIfExists('voip_providers');
    }
};
