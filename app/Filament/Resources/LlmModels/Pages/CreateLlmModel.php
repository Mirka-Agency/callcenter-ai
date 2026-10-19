<?php

namespace App\Filament\Resources\LlmModels\Pages;

use App\Application\Llm\Actions\SyncPlatformLlmDefaults;
use App\Filament\Resources\LlmModels\LlmModelResource;
use App\Models\LlmModel;
use Filament\Resources\Pages\CreateRecord;

class CreateLlmModel extends CreateRecord
{
    protected static string $resource = LlmModelResource::class;

    protected function afterCreate(): void
    {
        /** @var LlmModel $record */
        $record = $this->record;

        app(SyncPlatformLlmDefaults::class)->execute($record);
    }
}
