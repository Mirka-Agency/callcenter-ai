<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        IdempotentSchema::tableIfMissingColumns('customers', ['lifecycle_stage'], function (Blueprint $table) {
            $table->string('lifecycle_stage')->nullable()->after('owner_organization_user_id');
            $table->index(['organization_id', 'lifecycle_stage'], 'customers_org_lifecycle_index');
        });

        IdempotentSchema::create('customer_lifecycle_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('from_stage')->nullable();
            $table->string('to_stage')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source');
            $table->string('reason')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['organization_id', 'customer_id'], 'customer_lifecycle_events_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_lifecycle_events');

        if (Schema::hasTable('customers') && Schema::hasIndex('customers', 'customers_org_lifecycle_index')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropIndex('customers_org_lifecycle_index');
            });
        }

        IdempotentSchema::dropColumnsIfExist('customers', 'lifecycle_stage');
    }
};
