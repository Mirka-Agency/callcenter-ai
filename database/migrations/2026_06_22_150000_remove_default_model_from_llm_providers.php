<?php

use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\PlatformAiSettings;
use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $settings = PlatformAiSettings::current();

        if (! $settings->default_llm_model_id) {
            $provider = LlmProvider::query()
                ->whereNotNull('default_llm_model_id')
                ->orderBy('id')
                ->first();

            if ($provider?->default_llm_model_id) {
                $settings->update([
                    'default_llm_provider_id' => $provider->id,
                    'default_llm_model_id' => $provider->default_llm_model_id,
                ]);

                LlmModel::query()
                    ->whereKeyNot($provider->default_llm_model_id)
                    ->update(['is_default' => false]);

                LlmModel::query()
                    ->whereKey($provider->default_llm_model_id)
                    ->update(['is_default' => true]);
            }
        }

        IdempotentSchema::dropConstrainedForeignIdIfExists('llm_providers', 'default_llm_model_id');
    }

    public function down(): void
    {
        Schema::table('llm_providers', function (Blueprint $table) {
            if (! Schema::hasColumn('llm_providers', 'default_llm_model_id')) {
                $table->foreignId('default_llm_model_id')->nullable()->after('base_url')->constrained('llm_models')->nullOnDelete();
            }
        });

        $settings = PlatformAiSettings::current();

        if ($settings->default_llm_model_id && $settings->default_llm_provider_id) {
            LlmProvider::query()
                ->whereKey($settings->default_llm_provider_id)
                ->update(['default_llm_model_id' => $settings->default_llm_model_id]);
        }
    }
};
