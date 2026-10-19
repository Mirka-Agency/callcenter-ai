<?php

namespace App\Filament\Resources\LlmModels\Pages;

use App\Application\Llm\Actions\SyncPlatformLlmDefaults;
use App\Filament\Resources\LlmModels\LlmModelResource;
use App\Filament\Resources\LlmModels\Widgets\ModelCostBreakdownWidget;
use App\Models\LlmModel;
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
        /** @var LlmModel $record */
        $record = $this->record;

        app(SyncPlatformLlmDefaults::class)->execute($record);
    }
}
