<?php

namespace App\Application\Llm\Actions;

use App\Domain\Llm\Enums\LlmProviderCode;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\PlatformAiSettings;

class SetGpt4oTranscribeAsDefaultAction
{
    public const MODEL_KEY = 'gpt-4o-transcribe';

    public const REPLACED_MODEL_KEY = 'whisper-1';

    public function execute(): ?LlmModel
    {
        $current = PlatformAiSettings::current()->load('transcriptionModel.provider')->transcriptionModel;

        if ($current && ! in_array($current->model_key, [self::MODEL_KEY, self::REPLACED_MODEL_KEY], true)) {
            return $current;
        }

        $model = $this->findOrCreateModel($current?->provider);

        if (! $model) {
            return null;
        }

        LlmModel::query()->whereKeyNot($model->id)->update(['is_transcription_default' => false]);

        $model->update([
            'is_transcription_default' => true,
            'is_active' => true,
            'sends_audio_file' => true,
        ]);

        PlatformAiSettings::current()->update([
            'default_transcription_provider_id' => $model->provider_id,
            'default_transcription_model_id' => $model->id,
        ]);

        return $model->refresh();
    }

    private function findOrCreateModel(?LlmProvider $preferredProvider): ?LlmModel
    {
        $existing = LlmModel::query()
            ->with('provider')
            ->where('model_key', self::MODEL_KEY)
            ->get();

        if ($existing->isNotEmpty()) {
            return $existing->first(fn (LlmModel $model) => $model->provider_id === $preferredProvider?->id)
                ?? $existing->first(fn (LlmModel $model) => $this->supportsSpeechApi($model->provider))
                ?? $existing->first();
        }

        $provider = $this->speechProvider($preferredProvider);

        if (! $provider) {
            return null;
        }

        return LlmModel::query()->create([
            'provider_id' => $provider->id,
            'name' => 'GPT-4o Transcribe',
            'model_key' => self::MODEL_KEY,
            'input_price_per_million_tokens' => 0,
            'output_price_per_million_tokens' => 0,
            'is_default' => false,
            'is_transcription_default' => true,
            'is_active' => true,
            'sends_audio_file' => true,
        ]);
    }

    private function speechProvider(?LlmProvider $preferredProvider): ?LlmProvider
    {
        if ($this->supportsSpeechApi($preferredProvider)) {
            return $preferredProvider;
        }

        return LlmProvider::query()
            ->whereRaw('LOWER(name) = ?', ['avalai'])
            ->where('is_active', true)
            ->first()
            ?? LlmProvider::query()
                ->whereIn('code', [
                    LlmProviderCode::OpenAi->value,
                    LlmProviderCode::OpenRouter->value,
                    LlmProviderCode::Custom->value,
                ])
                ->where('is_active', true)
                ->orderBy('id')
                ->first();
    }

    private function supportsSpeechApi(?LlmProvider $provider): bool
    {
        if (! $provider?->is_active) {
            return false;
        }

        return in_array($provider->code, [
            LlmProviderCode::OpenAi->value,
            LlmProviderCode::OpenRouter->value,
            LlmProviderCode::Custom->value,
        ], true);
    }
}
