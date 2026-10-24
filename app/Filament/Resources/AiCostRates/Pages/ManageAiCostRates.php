<?php

namespace App\Filament\Resources\AiCostRates\Pages;

use App\Filament\Resources\AiCostRates\AiCostRateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAiCostRates extends ManageRecords
{
    protected static string $resource = AiCostRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
