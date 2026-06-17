<?php

namespace App\Filament\Resources\LlmModels\Pages;

use App\Filament\Resources\LlmModels\LlmModelResource;
use App\Models\LlmModel;
use App\Models\PlatformAiSettings;
use Filament\Resources\Pages\CreateRecord;

class CreateLlmModel extends CreateRecord
{
    protected static string $resource = LlmModelResource::class;

    protected function afterCreate(): void
    {
        $this->syncDefaultModel();
    }

    private function syncDefaultModel(): void
    {
        /** @var LlmModel $record */
        $record = $this->record;

        if (! $record->is_default) {
            $settings = PlatformAiSettings::current();

            if ($settings->default_llm_model_id === $record->id) {
                $settings->update([
                    'default_llm_provider_id' => null,
                    'default_llm_model_id' => null,
                ]);
            }

            return;
        }

        LlmModel::query()
            ->whereKeyNot($record->id)
            ->update(['is_default' => false]);

        PlatformAiSettings::current()->update([
            'default_llm_provider_id' => $record->provider_id,
            'default_llm_model_id' => $record->id,
        ]);
    }
}
