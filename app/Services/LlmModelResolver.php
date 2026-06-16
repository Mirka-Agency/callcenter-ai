<?php

namespace App\Services;

use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\PlatformAiSettings;

class LlmModelResolver
{
    public function resolveForOrganization(int $organizationId): LlmModel
    {
        unset($organizationId);

        $platform = PlatformAiSettings::current()->load('defaultModel.provider');

        if ($platform->defaultModel?->is_active) {
            return $platform->defaultModel;
        }

        $providerModel = LlmProvider::query()
            ->where('is_active', true)
            ->whereNotNull('default_llm_model_id')
            ->first()
            ?->defaultModel;

        if ($providerModel?->is_active) {
            return $providerModel;
        }

        $fallback = LlmModel::query()->where('is_active', true)->where('is_default', true)->first()
            ?? LlmModel::query()->where('is_active', true)->first();

        if (! $fallback) {
            throw new \RuntimeException('No active LLM model configured. Set the platform default model in admin billing settings.');
        }

        return $fallback;
    }

    public function resolveProviderForOrganization(int $organizationId): ?LlmProvider
    {
        return $this->resolveForOrganization($organizationId)->provider;
    }

    public function overviewForOrganization(int $organizationId): array
    {
        $model = $this->resolveForOrganization($organizationId);

        return [
            'model_id' => $model->id,
            'model_name' => $model->name,
            'model_key' => $model->model_key,
            'provider_name' => $model->provider?->name,
            'provider_code' => $model->provider?->code,
            'input_price_per_million' => PlatformAiSettings::convertFromUnits((float) $model->input_price_per_million_tokens),
            'output_price_per_million' => PlatformAiSettings::convertFromUnits((float) $model->output_price_per_million_tokens),
        ];
    }
}
