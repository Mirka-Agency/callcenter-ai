<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_analyses', function (Blueprint $table) {
            if (! Schema::hasColumn('conversation_analyses', 'is_evaluable')) {
                $table->boolean('is_evaluable')->default(true)->after('score');
            }
        });

        DB::table('conversation_analyses')
            ->where('score', '=', 0)
            ->update(['is_evaluable' => false]);
    }

    public function down(): void
    {
        Schema::table('conversation_analyses', function (Blueprint $table) {
            if (Schema::hasColumn('conversation_analyses', 'is_evaluable')) {
                $table->dropColumn('is_evaluable');
            }
        });
    }
};
