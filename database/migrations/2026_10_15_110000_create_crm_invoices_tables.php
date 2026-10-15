<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        IdempotentSchema::create('crm_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 16);
            $table->string('status', 16)->default('draft');
            $table->string('number', 64)->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('buyer_name')->nullable();
            $table->string('buyer_company')->nullable();
            $table->string('buyer_phone', 64)->nullable();
            $table->string('buyer_national_id', 64)->nullable();
            $table->string('buyer_economic_code', 64)->nullable();
            $table->text('buyer_address')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('tax_percent', 5, 2)->default(10);
            $table->unsignedBigInteger('subtotal_rials')->default(0);
            $table->unsignedBigInteger('tax_rials')->default(0);
            $table->unsignedBigInteger('total_rials')->default(0);
            $table->boolean('notify_sms_on_issue')->default(false);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'document_type', 'status'], 'crm_invoices_org_type_status_idx');
            $table->index(['organization_id', 'number'], 'crm_invoices_org_number_idx');
            $table->index(['organization_id', 'updated_at'], 'crm_invoices_org_updated_idx');
            $table->index(['organization_id', 'customer_id'], 'crm_invoices_org_customer_idx');
        });

        IdempotentSchema::create('crm_invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_invoice_id')->constrained('crm_invoices')->cascadeOnDelete();
            $table->foreignId('crm_product_id')->nullable()->constrained('crm_products')->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 14, 2)->default(1);
            $table->string('unit_label', 64)->nullable();
            $table->unsignedBigInteger('unit_price_rials')->default(0);
            $table->unsignedBigInteger('amount_rials')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['crm_invoice_id', 'sort_order'], 'crm_invoice_lines_invoice_sort_idx');
            $table->index(['organization_id', 'crm_invoice_id'], 'crm_invoice_lines_org_invoice_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_invoice_lines');
        Schema::dropIfExists('crm_invoices');
    }
};
