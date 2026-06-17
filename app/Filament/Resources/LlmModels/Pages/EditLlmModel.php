<?php

namespace App\Filament\Resources\LlmModels\Pages;

use App\Filament\Resources\LlmModels\LlmModelResource;
use App\Filament\Resources\LlmModels\Widgets\ModelCostBreakdownWidget;
use App\Models\LlmModel;
use App\Models\PlatformAiSettings;
use Filament\Resources\Pages\EditRecord;

class EditLlmModel extends EditRecord
{
    protected static string $resource = LlmModelResource::class;

    /**
     * @return array<class-string<\Filament\Widgets\Widget> | \Filament\Widgets\WidgetConfiguration>
     */
    protected function getFooterWidgets(): array
    {
        return [
            ModelCostBreakdownWidget::make(['record' => $this->getRecord()]),
        ];
    }

    protected function afterSave(): void
    {
        $this->syncDefaultModel();
    }

    private function syncDefaultModel(): void
    {
        /** @var LlmModel $record */
        $record = $this->record->refresh();

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
