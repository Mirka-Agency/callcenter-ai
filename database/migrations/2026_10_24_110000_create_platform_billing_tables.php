<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        IdempotentSchema::tableIfMissingColumns('organizations', ['legal_name'], function (Blueprint $table) {
            $table->string('legal_name')->nullable();
            $table->string('national_id', 20)->nullable();
            $table->string('economic_code', 20)->nullable();
            $table->text('billing_address')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('billing_phone', 30)->nullable();
        });

        IdempotentSchema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price_rials');
            $table->string('billing_period')->default('monthly');
            $table->unsignedInteger('call_quota')->nullable();
            $table->unsignedInteger('seat_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        IdempotentSchema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('discount_type');
            $table->unsignedBigInteger('discount_value');
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redemptions')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        IdempotentSchema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status');
            $table->unsignedBigInteger('price_rials');
            $table->string('billing_period');
            $table->timestamp('starts_at');
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        IdempotentSchema::create('billing_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number')->unique();
            $table->string('status')->default('draft');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('subtotal_rials')->default(0);
            $table->unsignedBigInteger('discount_rials')->default(0);
            $table->decimal('tax_rate', 5, 2)->default(10);
            $table->unsignedBigInteger('tax_rials')->default(0);
            $table->unsignedBigInteger('total_rials')->default(0);
            $table->json('buyer')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_at']);
        });

        IdempotentSchema::create('billing_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price_rials');
            $table->unsignedBigInteger('amount_rials');
            $table->timestamps();
        });

        IdempotentSchema::create('billing_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('payment');
            $table->unsignedBigInteger('amount_rials');
            $table->timestamp('paid_at');
            $table->string('method');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('paid_at');
        });

        IdempotentSchema::create('ai_cost_rates', function (Blueprint $table) {
            $table->id();
            $table->string('model_name');
            $table->unsignedBigInteger('input_cost_per_million_rials');
            $table->unsignedBigInteger('output_cost_per_million_rials');
            $table->date('effective_from');
            $table->timestamps();

            $table->index(['model_name', 'effective_from']);
        });

        IdempotentSchema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        IdempotentSchema::create('marketing_spends', function (Blueprint $table) {
            $table->id();
            $table->date('spent_on');
            $table->string('channel');
            $table->unsignedBigInteger('amount_rials');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('spent_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_spends');
        Schema::dropIfExists('admin_settings');
        Schema::dropIfExists('ai_cost_rates');
        Schema::dropIfExists('billing_payments');
        Schema::dropIfExists('billing_invoice_lines');
        Schema::dropIfExists('billing_invoices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('plans');
        IdempotentSchema::dropColumnsIfExist(
            'organizations',
            'legal_name', 'national_id', 'economic_code', 'billing_address', 'postal_code', 'billing_phone',
        );
    }
};
