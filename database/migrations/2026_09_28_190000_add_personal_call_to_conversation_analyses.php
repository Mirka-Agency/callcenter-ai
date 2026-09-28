<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_analyses', function (Blueprint $table) {
            if (! Schema::hasColumn('conversation_analyses', 'is_personal')) {
                $table->boolean('is_personal')->default(false)->after('is_evaluable');
            }

            if (! Schema::hasColumn('conversation_analyses', 'personal_reason')) {
                $table->text('personal_reason')->nullable()->after('is_personal');
            }

            if (! Schema::hasIndex('conversation_analyses', 'conversation_analyses_user_personal_idx')) {
                $table->index(
                    ['organization_user_id', 'is_personal', 'analyzed_at'],
                    'conversation_analyses_user_personal_idx',
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('conversation_analyses', function (Blueprint $table) {
            if (Schema::hasIndex('conversation_analyses', 'conversation_analyses_user_personal_idx')) {
                $table->dropIndex('conversation_analyses_user_personal_idx');
            }

            if (Schema::hasColumn('conversation_analyses', 'personal_reason')) {
                $table->dropColumn('personal_reason');
            }

            if (Schema::hasColumn('conversation_analyses', 'is_personal')) {
                $table->dropColumn('is_personal');
            }
        });
    }
};
