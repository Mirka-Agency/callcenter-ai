<?php

namespace Database\Seeders;

use App\Application\Llm\Actions\SetGemini38FlashAsDefaultAction;
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
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'provider_id' => $gemini->id,
                'name' => SetGemini38FlashAsDefaultAction::MODEL_KEY,
                'model_key' => SetGemini38FlashAsDefaultAction::MODEL_KEY,
                'input_price_per_million_tokens' => 0.75,
                'output_price_per_million_tokens' => 3.75,
                'is_default' => true,
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

        app(SetGemini38FlashAsDefaultAction::class)->execute();
    }
}
