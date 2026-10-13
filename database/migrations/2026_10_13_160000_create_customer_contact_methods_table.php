<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        IdempotentSchema::create('customer_contact_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('raw_value');
            $table->string('normalized_value');
            $table->string('label')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('verification_status')->default('unverified');
            $table->string('source')->default('system');
            $table->timestamps();

            $table->unique(['customer_id', 'type', 'normalized_value'], 'customer_contact_methods_unique');
            $table->index(['organization_id', 'type', 'normalized_value'], 'customer_contact_methods_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_contact_methods');
    }
};
