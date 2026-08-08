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
            if (! Schema::hasColumn('organizations', 'business_context')) {
                $table->text('business_context')->nullable()->after('title');
            }
        });
    }

    public function down(): void
    {
        IdempotentSchema::dropColumnsIfExist('organizations', 'business_context');
    }
};
