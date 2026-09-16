<?php

namespace Database\Seeders;

use App\Domain\Llm\Enums\LlmProviderCode;
use App\Models\LlmPromptVersion;
use App\Models\LlmProvider;
use Illuminate\Database\Seeder;

class LlmProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            [
                'name' => 'OpenAI',
                'code' => LlmProviderCode::OpenAi->value,
                'config' => [
                    'default_api_url' => 'https://api.openai.com/v1',
                    'default_model' => 'gpt-4o-mini',
                    'transcription_model' => 'whisper-1',
                ],
            ],
            [
                'name' => 'Anthropic',
                'code' => LlmProviderCode::Anthropic->value,
                'config' => [
                    'default_api_url' => 'https://api.anthropic.com/v1',
                    'default_model' => 'claude-3-5-sonnet-latest',
                ],
            ],
            [
                'name' => 'Google Gemini',
                'code' => LlmProviderCode::Gemini->value,
                'config' => [
                    'default_api_url' => 'https://generativelanguage.googleapis.com/v1beta',
                    'default_model' => 'gemini-2.0-flash',
                ],
            ],
            [
                'name' => 'OpenRouter',
                'code' => LlmProviderCode::OpenRouter->value,
                'config' => [
                    'default_api_url' => 'https://openrouter.ai/api/v1',
                    'default_model' => 'openai/gpt-4o-mini',
                ],
            ],
            [
                'name' => 'Custom LLM',
                'code' => LlmProviderCode::Custom->value,
                'config' => [],
            ],
        ];

        foreach ($providers as $provider) {
            LlmProvider::query()->updateOrCreate(
                ['code' => $provider['code']],
                $provider,
            );
        }

        LlmPromptVersion::query()->updateOrCreate(
            ['version' => 'v1'],
            [
                'name' => 'تحلیل پیش‌فرض کیفیت تماس',
                'system_prompt' => app(\App\Application\Llm\Services\PromptBuilder::class)->defaultSystemPrompt(),
                'is_active' => true,
            ],
        );
    }
}
