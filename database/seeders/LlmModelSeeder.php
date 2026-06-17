<?php

namespace Database\Seeders;

use App\Domain\Llm\Enums\LlmProviderCode;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use Illuminate\Database\Seeder;

class LlmModelSeeder extends Seeder
{
    public function run(): void
    {
        $openAi = LlmProvider::query()->where('code', LlmProviderCode::OpenAi->value)->firstOrFail();
        $gemini = LlmProvider::query()->where('code', LlmProviderCode::Gemini->value)->firstOrFail();
        $anthropic = LlmProvider::query()->where('code', LlmProviderCode::Anthropic->value)->firstOrFail();

        $models = [
            [
                'provider_id' => $openAi->id,
                'name' => 'GPT-5',
                'model_key' => 'gpt-5',
                'input_price_per_million_tokens' => 1.25,
                'output_price_per_million_tokens' => 10.00,
                'is_default' => true,
                'is_active' => true,
            ],
            [
                'provider_id' => $gemini->id,
                'name' => 'Gemini Pro',
                'model_key' => 'gemini-1.5-pro',
                'input_price_per_million_tokens' => 0.35,
                'output_price_per_million_tokens' => 2.50,
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'provider_id' => $anthropic->id,
                'name' => 'Claude Sonnet',
                'model_key' => 'claude-3-5-sonnet-latest',
                'input_price_per_million_tokens' => 3.00,
                'output_price_per_million_tokens' => 15.00,
                'is_default' => false,
                'is_active' => true,
            ],
        ];

        foreach ($models as $model) {
            LlmModel::query()->updateOrCreate(
                ['provider_id' => $model['provider_id'], 'model_key' => $model['model_key']],
                $model,
            );
        }

        $defaultModel = LlmModel::query()->where('is_default', true)->first()
            ?? LlmModel::query()->first();

        if ($defaultModel) {
            \App\Models\PlatformAiSettings::current()->update([
                'default_llm_provider_id' => $defaultModel->provider_id,
                'default_llm_model_id' => $defaultModel->id,
            ]);
        }
    }
}
