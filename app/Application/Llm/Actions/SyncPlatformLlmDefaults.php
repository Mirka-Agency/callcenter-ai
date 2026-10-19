<?php

namespace App\Application\Llm\Actions;

use App\Models\LlmModel;
use App\Models\PlatformAiSettings;

class SyncPlatformLlmDefaults
{
    public function execute(LlmModel $model): void
    {
        $model = $model->refresh();

        $this->syncFlag(
            $model,
            enabled: (bool) $model->is_default,
            flagColumn: 'is_default',
            providerColumn: 'default_llm_provider_id',
            modelColumn: 'default_llm_model_id',
        );

        $this->syncFlag(
            $model,
            enabled: (bool) $model->is_transcription_default,
            flagColumn: 'is_transcription_default',
            providerColumn: 'default_transcription_provider_id',
            modelColumn: 'default_transcription_model_id',
        );
    }

    private function syncFlag(
        LlmModel $model,
        bool $enabled,
        string $flagColumn,
        string $providerColumn,
        string $modelColumn,
    ): void {
        $settings = PlatformAiSettings::current();

        if (! $enabled) {
            if ($settings->{$modelColumn} === $model->id) {
                $settings->update([
                    $providerColumn => null,
                    $modelColumn => null,
                ]);
            }

            return;
        }

        LlmModel::query()
            ->whereKeyNot($model->id)
            ->update([$flagColumn => false]);

        $settings->update([
            $providerColumn => $model->provider_id,
            $modelColumn => $model->id,
        ]);
    }
}
