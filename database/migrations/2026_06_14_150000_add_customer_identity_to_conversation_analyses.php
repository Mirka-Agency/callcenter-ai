<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_analyses', function (Blueprint $table) {
            if (! Schema::hasColumn('conversation_analyses', 'customer_identity_json')) {
                $table->json('customer_identity_json')->nullable()->after('concerns_json');
            }
        });
    }

    public function down(): void
    {
        IdempotentSchema::dropColumnsIfExist('conversation_analyses', 'customer_identity_json');
    }
};
