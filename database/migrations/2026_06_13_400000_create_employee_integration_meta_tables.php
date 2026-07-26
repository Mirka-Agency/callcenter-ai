<?php

use App\Models\OrganizationCrmConnection;
use App\Models\OrganizationVoipConnection;
use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        IdempotentSchema::create('integration_meta_definitions', function (Blueprint $table) {
            $table->id();
            $table->morphs('provider');
            $table->string('name');
            $table->string('key');
            $table->string('field_type')->default('text');
            $table->boolean('is_required')->default(false);
            $table->string('placeholder')->nullable();
            $table->text('help_text')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['provider_type', 'provider_id', 'key'], 'imd_provider_key_unique');
        });

        IdempotentSchema::create('employee_integration_meta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_user_id')->constrained('organization_user')->cascadeOnDelete();
            $table->morphs('integratable');
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(
                ['organization_user_id', 'integratable_type', 'integratable_id', 'key'],
                'employee_integration_meta_unique',
            );
        });

        $this->migrateLegacyPivotData();

        IdempotentSchema::dropConstrainedForeignIdIfExists('organization_user', 'organization_voip_connection_id');
        IdempotentSchema::dropConstrainedForeignIdIfExists('organization_user', 'organization_crm_connection_id');
        IdempotentSchema::dropColumnsIfExist('organization_user', 'extension_number');
    }

    public function down(): void
    {
        Schema::table('organization_user', function (Blueprint $table) {
            if (! Schema::hasColumn('organization_user', 'extension_number')) {
                $table->string('extension_number')->nullable()->after('mobile');
            }
            if (! Schema::hasColumn('organization_user', 'organization_crm_connection_id')) {
                $table->foreignId('organization_crm_connection_id')
                    ->nullable()
                    ->constrained('organization_crm_connections')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('organization_user', 'organization_voip_connection_id')) {
                $table->foreignId('organization_voip_connection_id')
                    ->nullable()
                    ->constrained('organization_voip_connections')
                    ->nullOnDelete();
            }
        });

        Schema::dropIfExists('employee_integration_meta');
        Schema::dropIfExists('integration_meta_definitions');
    }

    private function migrateLegacyPivotData(): void
    {
        if (! Schema::hasColumn('organization_user', 'extension_number')) {
            return;
        }

        if (! Schema::hasTable('employee_integration_meta')) {
            return;
        }

        $memberships = DB::table('organization_user')->get();

        foreach ($memberships as $membership) {
            if ($membership->organization_voip_connection_id && $membership->extension_number) {
                $exists = DB::table('employee_integration_meta')
                    ->where('organization_user_id', $membership->id)
                    ->where('integratable_type', OrganizationVoipConnection::class)
                    ->where('integratable_id', $membership->organization_voip_connection_id)
                    ->where('key', 'extension')
                    ->exists();

                if (! $exists) {
                    DB::table('employee_integration_meta')->insert([
                        'organization_user_id' => $membership->id,
                        'integratable_type' => OrganizationVoipConnection::class,
                        'integratable_id' => $membership->organization_voip_connection_id,
                        'key' => 'extension',
                        'value' => $membership->extension_number,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            if ($membership->organization_crm_connection_id && $membership->mobile) {
                $exists = DB::table('employee_integration_meta')
                    ->where('organization_user_id', $membership->id)
                    ->where('integratable_type', OrganizationCrmConnection::class)
                    ->where('integratable_id', $membership->organization_crm_connection_id)
                    ->where('key', 'mobile')
                    ->exists();

                if (! $exists) {
                    DB::table('employee_integration_meta')->insert([
                        'organization_user_id' => $membership->id,
                        'integratable_type' => OrganizationCrmConnection::class,
                        'integratable_id' => $membership->organization_crm_connection_id,
                        'key' => 'mobile',
                        'value' => $membership->mobile,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
};
