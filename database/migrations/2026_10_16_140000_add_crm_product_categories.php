<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        IdempotentSchema::create('crm_product_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('slug', 80);
            $table->string('icon', 32)->default('layers');
            $table->string('tone', 32)->default('violet');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['organization_id', 'slug'], 'crm_product_categories_org_slug_uq');
            $table->index(['organization_id', 'position'], 'crm_product_categories_org_pos_idx');
        });

        IdempotentSchema::tableIfMissingColumns('crm_products', ['category_id'], function (Blueprint $table): void {
            $table->foreignId('category_id')
                ->nullable()
                ->after('type')
                ->constrained('crm_product_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        IdempotentSchema::dropConstrainedForeignIdIfExists('crm_products', 'category_id');
        Schema::dropIfExists('crm_product_categories');
    }
};
