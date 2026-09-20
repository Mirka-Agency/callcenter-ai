<?php

namespace App\Application\Llm\Actions;

use App\Domain\Llm\Enums\LlmProviderCode;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\PlatformAiSettings;

class SetGemini38FlashAsDefaultAction
{
    public const MODEL_KEY = 'gemini-3.8-flash';

    public function execute(): ?LlmModel
    {
        $model = $this->findOrCreateModel();

        if (! $model) {
            return null;
        }

        LlmModel::query()->whereKeyNot($model->id)->update(['is_default' => false]);

        $model->update([
            'is_default' => true,
            'is_active' => true,
        ]);

        PlatformAiSettings::current()->update([
            'default_llm_provider_id' => $model->provider_id,
            'default_llm_model_id' => $model->id,
        ]);

        return $model->refresh();
    }

    private function findOrCreateModel(): ?LlmModel
    {
        $existing = LlmModel::query()
            ->with('provider')
            ->where('model_key', self::MODEL_KEY)
            ->get();

        if ($existing->isNotEmpty()) {
            return $existing->first(fn (LlmModel $model) => strcasecmp((string) $model->provider?->name, 'avalai') === 0)
                ?? $existing->first(fn (LlmModel $model) => $model->provider?->code === LlmProviderCode::Gemini->value)
                ?? $existing->first();
        }

        $provider = $this->resolveProvider();

        if (! $provider) {
            return null;
        }

        return LlmModel::query()->create([
            'provider_id' => $provider->id,
            'name' => self::MODEL_KEY,
            'model_key' => self::MODEL_KEY,
            'input_price_per_million_tokens' => 0.75,
            'output_price_per_million_tokens' => 3.75,
            'cached_input_price_per_million_tokens' => 0.075,
            'is_default' => true,
            'is_active' => true,
            'sends_audio_file' => true,
        ]);
    }

    private function resolveProvider(): ?LlmProvider
    {
        return LlmProvider::query()
            ->whereRaw('LOWER(name) = ?', ['avalai'])
            ->where('is_active', true)
            ->first()
            ?? LlmProvider::query()
                ->where('code', LlmProviderCode::Gemini->value)
                ->where('is_active', true)
                ->first()
            ?? PlatformAiSettings::current()->load('defaultModel.provider')->defaultModel?->provider
            ?? LlmProvider::query()->where('is_active', true)->orderBy('id')->first();
    }
}
