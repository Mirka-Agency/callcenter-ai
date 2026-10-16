<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        IdempotentSchema::create('crm_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type', 16);
            $table->string('code', 64)->nullable();
            $table->unsignedBigInteger('price_tomans')->nullable();
            $table->decimal('unit_quantity', 14, 2)->nullable();
            $table->string('unit_label', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'type', 'is_active'], 'crm_products_org_type_active_idx');
            $table->index(['organization_id', 'name'], 'crm_products_org_name_idx');
            $table->index(['organization_id', 'code'], 'crm_products_org_code_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_products');
    }
};
