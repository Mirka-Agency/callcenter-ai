<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('organization_wallets');
        Schema::dropIfExists('ai_usage_daily_snapshots');
        Schema::dropIfExists('landing_calculator_models');

        IdempotentSchema::dropColumnsIfExist(
            'llm_models',
            'input_price_per_million_tokens',
            'output_price_per_million_tokens',
            'cached_input_price_per_million_tokens',
            'reasoning_price_per_million_tokens',
        );

        IdempotentSchema::dropColumnsIfExist(
            'conversation_analyses',
            'cost',
            'input_price_snapshot',
            'output_price_snapshot',
            'cached_input_price_snapshot',
            'reasoning_price_snapshot',
        );

        IdempotentSchema::dropColumnsIfExist(
            'platform_ai_settings',
            'allow_negative_balance',
            'currency',
            'billing_unit_currency',
            'billing_unit_price',
            'estimation_words_per_minute',
            'estimation_tokens_per_word',
            'estimation_conversation_ratios',
        );
    }

    public function down(): void
    {
        // Billing tables and price columns were removed intentionally.
    }
};
