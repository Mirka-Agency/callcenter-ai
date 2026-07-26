<?php

namespace Tests\Unit;

use Database\Support\IdempotentSchema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IdempotentSchemaTest extends TestCase
{
    public function test_create_skips_when_table_already_exists(): void
    {
        Schema::dropIfExists('idempotent_schema_probe');

        IdempotentSchema::create('idempotent_schema_probe', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        IdempotentSchema::create('idempotent_schema_probe', function (Blueprint $table): void {
            $table->id();
            $table->string('should_not_run');
        });

        $this->assertTrue(Schema::hasTable('idempotent_schema_probe'));
        $this->assertTrue(Schema::hasColumn('idempotent_schema_probe', 'name'));
        $this->assertFalse(Schema::hasColumn('idempotent_schema_probe', 'should_not_run'));

        Schema::dropIfExists('idempotent_schema_probe');
    }

    public function test_drop_helpers_are_safe_when_missing(): void
    {
        Schema::dropIfExists('idempotent_schema_probe');

        IdempotentSchema::create('idempotent_schema_probe', function (Blueprint $table): void {
            $table->id();
        });

        IdempotentSchema::dropColumnsIfExist('idempotent_schema_probe', 'missing_col');
        IdempotentSchema::dropConstrainedForeignIdIfExists('idempotent_schema_probe', 'missing_fk');

        $this->assertTrue(Schema::hasTable('idempotent_schema_probe'));

        Schema::dropIfExists('idempotent_schema_probe');
    }
}
