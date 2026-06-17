<?php

namespace App\Filament\Resources\LlmProviders\Pages;

use App\Filament\Resources\LlmProviders\LlmProviderResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLlmProvider extends EditRecord
{
    protected static string $resource = LlmProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->modalDescription(__('filament.misc.delete_provider_warning')),
        ];
    }
}
