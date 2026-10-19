<?php

namespace App\Filament\Resources\LandingCalculatorModels\Pages;

use App\Filament\Resources\LandingCalculatorModels\LandingCalculatorModelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLandingCalculatorModels extends ListRecords
{
    protected static string $resource = LandingCalculatorModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
