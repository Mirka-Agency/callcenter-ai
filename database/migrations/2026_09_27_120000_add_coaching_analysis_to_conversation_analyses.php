<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_analyses', function (Blueprint $table) {
            if (! Schema::hasColumn('conversation_analyses', 'coaching_analysis_json')) {
                $table->json('coaching_analysis_json')->nullable()->after('attention_json');
            }
        });
    }

    public function down(): void
    {
        Schema::table('conversation_analyses', function (Blueprint $table) {
            if (Schema::hasColumn('conversation_analyses', 'coaching_analysis_json')) {
                $table->dropColumn('coaching_analysis_json');
            }
        });
    }
};
