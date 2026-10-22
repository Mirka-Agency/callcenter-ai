<?php

namespace Database\Seeders;

use App\Application\Llm\Actions\SetGemini38FlashAsDefaultAction;
use App\Domain\Llm\Enums\LlmProviderCode;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\PlatformAiSettings;
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
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'provider_id' => $gemini->id,
                'name' => SetGemini38FlashAsDefaultAction::MODEL_KEY,
                'model_key' => SetGemini38FlashAsDefaultAction::MODEL_KEY,
                'is_default' => true,
                'is_active' => true,
            ],
            [
                'provider_id' => $anthropic->id,
                'name' => 'Claude Sonnet',
                'model_key' => 'claude-3-5-sonnet-latest',
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

        $this->seedTranscriptionDefault($openAi);
    }

    private function seedTranscriptionDefault(LlmProvider $openAi): void
    {
        $whisper = LlmModel::query()->updateOrCreate(
            ['provider_id' => $openAi->id, 'model_key' => 'whisper-1'],
            [
                'name' => 'Whisper',
                'is_default' => false,
                'is_active' => true,
                'sends_audio_file' => true,
            ],
        );

        $settings = PlatformAiSettings::current();

        if ($settings->default_transcription_model_id) {
            return;
        }

        LlmModel::query()
            ->whereKeyNot($whisper->id)
            ->update(['is_transcription_default' => false]);

        $whisper->update(['is_transcription_default' => true]);

        $settings->update([
            'default_transcription_provider_id' => $openAi->id,
            'default_transcription_model_id' => $whisper->id,
        ]);
    }
}
