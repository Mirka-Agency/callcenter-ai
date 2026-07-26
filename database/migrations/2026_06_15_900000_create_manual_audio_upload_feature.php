<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            if (! Schema::hasColumn('calls', 'source')) {
                $table->string('source')->default('voip')->after('provider_code');
            }
            if (! Schema::hasColumn('calls', 'uploader_id')) {
                $table->foreignId('uploader_id')->nullable()->after('organization_user_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('calls', 'uploader_type')) {
                $table->string('uploader_type')->nullable()->after('uploader_id');
            }
            if (! Schema::hasColumn('calls', 'title')) {
                $table->string('title')->nullable()->after('metadata');
            }
            if (! Schema::hasColumn('calls', 'customer_name')) {
                $table->string('customer_name')->nullable()->after('title');
            }
            if (! Schema::hasColumn('calls', 'customer_phone')) {
                $table->string('customer_phone')->nullable()->after('customer_name');
            }
            if (! Schema::hasColumn('calls', 'notes')) {
                $table->text('notes')->nullable()->after('customer_phone');
            }
            if (! Schema::hasColumn('calls', 'category')) {
                $table->string('category')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('calls', 'tags')) {
                $table->json('tags')->nullable()->after('category');
            }
            if (! Schema::hasColumn('calls', 'conversation_date')) {
                $table->timestamp('conversation_date')->nullable()->after('tags');
            }

            if (! Schema::hasIndex('calls', ['source'])) {
                $table->index('source');
            }
            if (! Schema::hasIndex('calls', ['organization_id', 'source'])) {
                $table->index(['organization_id', 'source']);
            }
        });

        Schema::table('conversation_analyses', function (Blueprint $table) {
            if (! Schema::hasColumn('conversation_analyses', 'source')) {
                $table->string('source')->default('voip')->after('call_id');
            }
            if (! Schema::hasIndex('conversation_analyses', ['source'])) {
                $table->index('source');
            }
        });

        IdempotentSchema::create('audio_upload_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('max_file_size_bytes')->default(52_428_800);
            $table->unsignedInteger('max_duration_seconds')->default(3600);
            $table->json('allowed_extensions');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audio_upload_settings');

        if (Schema::hasIndex('conversation_analyses', ['source'])) {
            Schema::table('conversation_analyses', function (Blueprint $table) {
                $table->dropIndex(['source']);
            });
        }
        IdempotentSchema::dropColumnsIfExist('conversation_analyses', 'source');

        if (Schema::hasIndex('calls', ['organization_id', 'source'])) {
            Schema::table('calls', function (Blueprint $table) {
                $table->dropIndex(['organization_id', 'source']);
            });
        }
        if (Schema::hasIndex('calls', ['source'])) {
            Schema::table('calls', function (Blueprint $table) {
                $table->dropIndex(['source']);
            });
        }
        IdempotentSchema::dropConstrainedForeignIdIfExists('calls', 'uploader_id');
        IdempotentSchema::dropColumnsIfExist(
            'calls',
            'source',
            'uploader_type',
            'title',
            'customer_name',
            'customer_phone',
            'notes',
            'category',
            'tags',
            'conversation_date',
        );
    }
};
