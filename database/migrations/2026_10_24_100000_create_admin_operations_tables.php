<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEAD_TABLES = ['demo_requests', 'consultation_requests', 'contact_messages'];

    public function up(): void
    {
        IdempotentSchema::create('user_activity_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->date('activity_date');
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'activity_date']);
            $table->index(['activity_date', 'role']);
        });

        IdempotentSchema::create('admin_notes', function (Blueprint $table) {
            $table->id();
            $table->morphs('notable');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        IdempotentSchema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->nullableMorphs('auditable');
            $table->string('auditable_label')->nullable();
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        IdempotentSchema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        IdempotentSchema::tableIfMissingColumns('users', ['admin_areas'], function (Blueprint $table) {
            $table->json('admin_areas')->nullable();
        });

        foreach (self::LEAD_TABLES as $leadTable) {
            IdempotentSchema::tableIfMissingColumns($leadTable, ['lead_status'], function (Blueprint $table) {
                $table->string('lead_status')->default('new')->index();
                $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('follow_up_at')->nullable();
                $table->timestamp('first_contacted_at')->nullable();
                $table->string('source')->nullable();
                $table->string('utm_source')->nullable();
                $table->string('utm_medium')->nullable();
                $table->string('utm_campaign')->nullable();
                $table->foreignId('converted_organization_id')->nullable()->constrained('organizations')->nullOnDelete();
                $table->string('lost_reason')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::LEAD_TABLES as $leadTable) {
            IdempotentSchema::dropConstrainedForeignIdIfExists($leadTable, 'assigned_to_user_id');
            IdempotentSchema::dropConstrainedForeignIdIfExists($leadTable, 'converted_organization_id');
            IdempotentSchema::dropColumnsIfExist(
                $leadTable,
                'lead_status', 'follow_up_at', 'first_contacted_at', 'source',
                'utm_source', 'utm_medium', 'utm_campaign', 'lost_reason',
            );
        }

        IdempotentSchema::dropColumnsIfExist('users', 'admin_areas');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('admin_audit_logs');
        Schema::dropIfExists('admin_notes');
        Schema::dropIfExists('user_activity_days');
    }
};
