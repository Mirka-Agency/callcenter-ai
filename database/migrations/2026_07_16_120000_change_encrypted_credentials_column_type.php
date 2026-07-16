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
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN credentials TYPE text USING credentials::text");

            return;
        }

        Schema::table($table, function (Blueprint $table): void {
            $table->text('credentials')->change();
        });
    }

    private function changeCredentialsColumnToJson(string $table): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN credentials TYPE json USING credentials::json");

            return;
        }

        Schema::table($table, function (Blueprint $table): void {
            $table->json('credentials')->change();
        });
    }
};
