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
            throw new \RuntimeException('No active LLM model configured. Mark one model as the platform default.');
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

    public function resolveTranscriptionForOrganization(int $organizationId): ?LlmModel
    {
        unset($organizationId);

        $platform = PlatformAiSettings::current()->load('transcriptionModel.provider');

        if ($this->isUsableModel($platform->transcriptionModel)) {
            return $platform->transcriptionModel;
        }

        $flagged = LlmModel::query()
            ->with('provider')
            ->where('is_active', true)
            ->where('is_transcription_default', true)
            ->whereHas('provider', fn ($query) => $query->where('is_active', true))
            ->first();

        return $this->isUsableModel($flagged) ? $flagged : null;
    }

    private function isUsableModel(?LlmModel $model): bool
    {
        return $model?->is_active && $model->provider?->is_active;
    }

    public function resolveProviderForOrganization(int $organizationId): ?LlmProvider
    {
        return $this->resolveForOrganization($organizationId)->provider;
    }
}
