<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * analyzed_at used to be set to the call's occurred time, so dashboard
 * "analyzed in last 30 days" followed call day instead of completion day.
 * updated_at is the closest record of when the analysis was actually written.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('conversation_analyses')
            ->whereNotNull('updated_at')
            ->update([
                'analyzed_at' => DB::raw('updated_at'),
            ]);
    }

    public function down(): void
    {
        // Irreversible: original call-time values were overwritten.
    }
};
