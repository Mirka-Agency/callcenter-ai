<?php

use App\Models\Customer;
use App\Models\CustomerCompany;
use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        IdempotentSchema::create('customer_companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('normalized_name');
            $table->string('industry')->nullable();
            $table->string('website')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('contacts_count')->default(0);
            $table->unsignedInteger('total_calls')->default(0);
            $table->unsignedTinyInteger('latest_lead_score')->nullable();
            $table->string('latest_lead_level')->nullable();
            $table->string('conversation_trend')->nullable();
            $table->text('recommended_next_action')->nullable();
            $table->timestamp('first_contact_at')->nullable();
            $table->timestamp('last_contact_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'normalized_name']);
            $table->index(['organization_id', 'last_contact_at']);
        });

        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'customer_company_id')) {
                $table->foreignId('customer_company_id')
                    ->nullable()
                    ->after('organization_id')
                    ->constrained('customer_companies')
                    ->nullOnDelete();
            }
        });

        $this->backfillCompaniesFromCustomers();
    }

    public function down(): void
    {
        IdempotentSchema::dropConstrainedForeignIdIfExists('customers', 'customer_company_id');

        Schema::dropIfExists('customer_companies');
    }

    private function backfillCompaniesFromCustomers(): void
    {
        $rows = DB::table('customers')
            ->select('id', 'organization_id', 'company_name')
            ->whereNotNull('company_name')
            ->where('company_name', '!=', '')
            ->whereNull('customer_company_id')
            ->orderBy('id')
            ->get();

        $companyIdsByKey = [];

        foreach ($rows as $row) {
            $normalized = CustomerCompany::normalizeName((string) $row->company_name);

            if ($normalized === '') {
                continue;
            }

            $key = $row->organization_id.'|'.$normalized;

            if (! isset($companyIdsByKey[$key])) {
                $existingId = DB::table('customer_companies')
                    ->where('organization_id', $row->organization_id)
                    ->where('normalized_name', $normalized)
                    ->value('id');

                $companyIdsByKey[$key] = $existingId ?? DB::table('customer_companies')->insertGetId([
                    'organization_id' => $row->organization_id,
                    'name' => trim((string) $row->company_name),
                    'normalized_name' => $normalized,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('customers')
                ->where('id', $row->id)
                ->update(['customer_company_id' => $companyIdsByKey[$key]]);
        }

        $companyIds = array_values(array_unique($companyIdsByKey));

        if ($companyIds === []) {
            return;
        }

        foreach ($companyIds as $companyId) {
            $company = CustomerCompany::query()->find($companyId);

            if ($company) {
                app(\App\Services\CustomerCompanyService::class)->refreshAggregates($company);
            }
        }
    }
};
