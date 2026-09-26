<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            if (! Schema::hasColumn('organizations', 'holiday_weekdays')) {
                $table->json('holiday_weekdays')->nullable()->after('business_context');
            }
        });
    }

    public function down(): void
    {
        IdempotentSchema::dropColumnsIfExist('organizations', 'holiday_weekdays');
    }
};
