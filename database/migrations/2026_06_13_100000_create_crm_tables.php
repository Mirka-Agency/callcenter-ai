<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        IdempotentSchema::create('crm_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();
        });

        IdempotentSchema::create('organization_crm_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_provider_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('credentials');
            $table->json('settings')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });

        IdempotentSchema::create('crm_connection_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_crm_connection_id')->constrained()->cascadeOnDelete();
            $table->string('operation');
            $table->string('status');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();
        });

        IdempotentSchema::create('crm_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_crm_connection_id')->constrained()->cascadeOnDelete();
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
        Schema::dropIfExists('crm_sync_logs');
        Schema::dropIfExists('crm_connection_logs');
        Schema::dropIfExists('organization_crm_connections');
        Schema::dropIfExists('crm_providers');
    }
};
