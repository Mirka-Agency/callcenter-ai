<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rows loaded with explicit ids (restore, copy) do not move PostgreSQL sequences.
 * The next insert then collides with calls_pkey and analysis never starts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        $tables = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('column_name', 'id')
            ->pluck('table_name');

        foreach ($tables as $table) {
            if (! is_string($table) || preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
                continue;
            }

            $sequence = DB::scalar('SELECT pg_get_serial_sequence(?, ?)', [$table, 'id']);

            if (! is_string($sequence) || $sequence === '') {
                continue;
            }

            $max = DB::table($table)->max('id');

            if ($max === null) {
                DB::statement('SELECT setval(?, 1, false)', [$sequence]);

                continue;
            }

            DB::statement('SELECT setval(?, ?, true)', [$sequence, (int) $max]);
        }
    }

    public function down(): void
    {
        // Sequence positions are derived from existing rows and are not reversed.
    }
};
