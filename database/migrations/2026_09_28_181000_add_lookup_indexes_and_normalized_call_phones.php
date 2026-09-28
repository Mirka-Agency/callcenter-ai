<?php

use App\Models\Call;
use App\Services\CustomerPhoneResolver;
use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        IdempotentSchema::tableIfMissingColumns('calls', ['normalized_caller_number'], function (Blueprint $table): void {
            $table->string('normalized_caller_number', 32)->nullable();
        });
        IdempotentSchema::tableIfMissingColumns('calls', ['normalized_receiver_number'], function (Blueprint $table): void {
            $table->string('normalized_receiver_number', 32)->nullable();
        });
        IdempotentSchema::tableIfMissingColumns('calls', ['normalized_customer_phone'], function (Blueprint $table): void {
            $table->string('normalized_customer_phone', 32)->nullable();
        });

        IdempotentSchema::tableIfMissingIndex('calls', 'calls_org_norm_caller_idx', function (Blueprint $table): void {
            $table->index(['organization_id', 'normalized_caller_number'], 'calls_org_norm_caller_idx');
        });
        IdempotentSchema::tableIfMissingIndex('calls', 'calls_org_norm_receiver_idx', function (Blueprint $table): void {
            $table->index(['organization_id', 'normalized_receiver_number'], 'calls_org_norm_receiver_idx');
        });
        IdempotentSchema::tableIfMissingIndex('calls', 'calls_org_norm_customer_phone_idx', function (Blueprint $table): void {
            $table->index(['organization_id', 'normalized_customer_phone'], 'calls_org_norm_customer_phone_idx');
        });

        if (Schema::hasColumn('calls', 'processing_status')) {
            IdempotentSchema::tableIfMissingIndex('calls', 'calls_org_processing_status_idx', function (Blueprint $table): void {
                $table->index(['organization_id', 'processing_status'], 'calls_org_processing_status_idx');
            });
        }

        if (Schema::hasTable('voip_call_logs')) {
            IdempotentSchema::tableIfMissingIndex('voip_call_logs', 'voip_logs_org_started_idx', function (Blueprint $table): void {
                $table->index(['organization_id', 'started_at'], 'voip_logs_org_started_idx');
            });
            IdempotentSchema::tableIfMissingIndex('voip_call_logs', 'voip_logs_org_status_idx', function (Blueprint $table): void {
                $table->index(['organization_id', 'status'], 'voip_logs_org_status_idx');
            });
        }

        if (! Schema::hasColumn('calls', 'normalized_caller_number')) {
            return;
        }

        $resolver = app(CustomerPhoneResolver::class);

        Call::query()
            ->select([
                'id',
                'caller_number',
                'receiver_number',
                'customer_phone',
                'normalized_caller_number',
                'normalized_receiver_number',
                'normalized_customer_phone',
            ])
            ->orderBy('id')
            ->chunkById(500, function ($calls) use ($resolver): void {
                foreach ($calls as $call) {
                    $caller = $resolver->normalize($call->caller_number);
                    $receiver = $resolver->normalize($call->receiver_number);
                    $customerPhone = $resolver->normalize($call->customer_phone);

                    if (
                        $call->normalized_caller_number === $caller
                        && $call->normalized_receiver_number === $receiver
                        && $call->normalized_customer_phone === $customerPhone
                    ) {
                        continue;
                    }

                    DB::table('calls')->where('id', $call->id)->update([
                        'normalized_caller_number' => $caller,
                        'normalized_receiver_number' => $receiver,
                        'normalized_customer_phone' => $customerPhone,
                    ]);
                }
            });
    }

    public function down(): void
    {
        foreach ([
            'calls' => [
                'calls_org_norm_caller_idx',
                'calls_org_norm_receiver_idx',
                'calls_org_norm_customer_phone_idx',
                'calls_org_processing_status_idx',
            ],
            'voip_call_logs' => [
                'voip_logs_org_started_idx',
                'voip_logs_org_status_idx',
            ],
        ] as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $index) {
                if (! IdempotentSchema::hasIndex($table, $index)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($index): void {
                    $blueprint->dropIndex($index);
                });
            }
        }

        IdempotentSchema::dropColumnsIfExist(
            'calls',
            'normalized_caller_number',
            'normalized_receiver_number',
            'normalized_customer_phone',
        );
    }
};
