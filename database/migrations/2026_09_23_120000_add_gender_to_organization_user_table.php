<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_user', function (Blueprint $table) {
            if (! Schema::hasColumn('organization_user', 'gender')) {
                $table->string('gender', 16)->nullable()->after('last_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('organization_user', function (Blueprint $table) {
            if (Schema::hasColumn('organization_user', 'gender')) {
                $table->dropColumn('gender');
            }
        });
    }
};
