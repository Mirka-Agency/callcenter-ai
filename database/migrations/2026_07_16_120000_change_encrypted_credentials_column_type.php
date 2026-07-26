<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->changeCredentialsColumnToText('organization_voip_connections');
        $this->changeCredentialsColumnToText('organization_crm_connections');
    }

    public function down(): void
    {
        $this->changeCredentialsColumnToJson('organization_voip_connections');
        $this->changeCredentialsColumnToJson('organization_crm_connections');
    }

    private function changeCredentialsColumnToText(string $table): void
    {
        if ($this->credentialsColumnType($table) === 'text') {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN credentials TYPE text USING credentials::text");

            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->text('credentials')->change();
        });
    }

    private function changeCredentialsColumnToJson(string $table): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN credentials TYPE json USING credentials::json");

            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->json('credentials')->change();
        });
    }

    private function credentialsColumnType(string $table): ?string
    {
        if (! Schema::hasColumn($table, 'credentials')) {
            return null;
        }

        if (DB::getDriverName() === 'mysql') {
            $row = DB::selectOne(
                'select DATA_TYPE as data_type from information_schema.columns
                 where table_schema = database() and table_name = ? and column_name = ?',
                [$table, 'credentials'],
            );

            return isset($row->data_type) ? strtolower((string) $row->data_type) : null;
        }

        if (DB::getDriverName() === 'pgsql') {
            $row = DB::selectOne(
                'select data_type from information_schema.columns
                 where table_schema = current_schema() and table_name = ? and column_name = ?',
                [$table, 'credentials'],
            );

            return isset($row->data_type) ? strtolower((string) $row->data_type) : null;
        }

        return null;
    }
};
