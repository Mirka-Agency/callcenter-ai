<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_user', function (Blueprint $table) {
            if (! Schema::hasColumn('organization_user', 'first_name')) {
                $table->string('first_name')->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('organization_user', 'last_name')) {
                $table->string('last_name')->nullable()->after('first_name');
            }
            if (! Schema::hasColumn('organization_user', 'mobile')) {
                $table->string('mobile')->nullable()->after('last_name');
            }
            if (! Schema::hasColumn('organization_user', 'extension_number')) {
                $table->string('extension_number')->nullable()->after('mobile');
            }
            if (! Schema::hasColumn('organization_user', 'position')) {
                $table->string('position')->nullable()->after('extension_number');
            }
            if (! Schema::hasColumn('organization_user', 'department')) {
                $table->string('department')->nullable()->after('position');
            }
            if (! Schema::hasColumn('organization_user', 'organization_crm_connection_id')) {
                $table->foreignId('organization_crm_connection_id')
                    ->nullable()
                    ->after('department')
                    ->constrained('organization_crm_connections')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('organization_user', 'organization_voip_connection_id')) {
                $table->foreignId('organization_voip_connection_id')
                    ->nullable()
                    ->after('organization_crm_connection_id')
                    ->constrained('organization_voip_connections')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('organization_user', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('organization_voip_connection_id');
            }
        });

        IdempotentSchema::create('organization_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_activities');

        IdempotentSchema::dropConstrainedForeignIdIfExists('organization_user', 'organization_voip_connection_id');
        IdempotentSchema::dropConstrainedForeignIdIfExists('organization_user', 'organization_crm_connection_id');
        IdempotentSchema::dropColumnsIfExist(
            'organization_user',
            'first_name',
            'last_name',
            'mobile',
            'extension_number',
            'position',
            'department',
            'is_active',
        );
    }
};
