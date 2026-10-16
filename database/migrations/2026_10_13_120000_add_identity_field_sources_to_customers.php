<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        IdempotentSchema::tableIfMissingColumns('customers', ['identity_field_sources'], function (Blueprint $table) {
            $table->json('identity_field_sources')->nullable()->after('identity_confidence');
        });
    }

    public function down(): void
    {
        IdempotentSchema::dropColumnsIfExist('customers', 'identity_field_sources');
    }
};
