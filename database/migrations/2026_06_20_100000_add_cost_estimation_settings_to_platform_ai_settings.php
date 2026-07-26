<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_ai_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_ai_settings', 'estimation_words_per_minute')) {
                $table->unsignedSmallInteger('estimation_words_per_minute')->default(150)->after('currency');
            }
            if (! Schema::hasColumn('platform_ai_settings', 'estimation_tokens_per_word')) {
                $table->decimal('estimation_tokens_per_word', 4, 2)->default(1.30)->after('estimation_words_per_minute');
            }
            if (! Schema::hasColumn('platform_ai_settings', 'estimation_conversation_ratios')) {
                $table->json('estimation_conversation_ratios')->nullable()->after('estimation_tokens_per_word');
            }
        });
    }

    public function down(): void
    {
        IdempotentSchema::dropColumnsIfExist(
            'platform_ai_settings',
            'estimation_words_per_minute',
            'estimation_tokens_per_word',
            'estimation_conversation_ratios',
        );
    }
};
