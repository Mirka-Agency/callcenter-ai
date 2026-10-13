<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        IdempotentSchema::tableIfMissingColumns('customers', ['owner_organization_user_id'], function (Blueprint $table) {
            $table->foreignId('owner_organization_user_id')
                ->nullable()
                ->after('customer_company_id')
                ->constrained('organization_user')
                ->nullOnDelete();
            $table->index(['organization_id', 'owner_organization_user_id'], 'customers_org_owner_index');
        });

        IdempotentSchema::tableIfMissingColumns('organizations', ['assign_customer_owner_from_call'], function (Blueprint $table) {
            $table->boolean('assign_customer_owner_from_call')->default(false);
        });

        IdempotentSchema::create('customer_owner_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('previous_owner_organization_user_id')->nullable();
            $table->unsignedBigInteger('new_owner_organization_user_id')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'customer_id'], 'customer_owner_events_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_owner_events');

        if (Schema::hasTable('customers') && Schema::hasIndex('customers', 'customers_org_owner_index')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropIndex('customers_org_owner_index');
            });
        }

        IdempotentSchema::dropConstrainedForeignIdIfExists('customers', 'owner_organization_user_id');
        IdempotentSchema::dropColumnsIfExist('organizations', 'assign_customer_owner_from_call');
    }
};
