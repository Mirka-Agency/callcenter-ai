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

        $model = $this->resolveActiveDefaultModel();

        if (! $model) {
            throw new \RuntimeException('No active LLM model configured. Mark one model as the platform default in Models & Pricing.');
        }

        return $model;
    }

    private function resolveActiveDefaultModel(): ?LlmModel
    {
        $platform = PlatformAiSettings::current()->load('defaultModel.provider');

        if ($this->isUsableModel($platform->defaultModel)) {
            return $platform->defaultModel;
        }

        $flaggedDefault = LlmModel::query()
            ->with('provider')
            ->where('is_active', true)
            ->where('is_default', true)
            ->whereHas('provider', fn ($query) => $query->where('is_active', true))
            ->first();

        if ($this->isUsableModel($flaggedDefault)) {
            return $flaggedDefault;
        }

        return LlmModel::query()
            ->with('provider')
            ->where('is_active', true)
            ->whereHas('provider', fn ($query) => $query->where('is_active', true))
            ->orderBy('id')
            ->first();
    }

    private function isUsableModel(?LlmModel $model): bool
    {
        return $model?->is_active && $model->provider?->is_active;
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
