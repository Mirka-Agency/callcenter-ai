<?php

namespace App\Filament\Resources\LandingCalculatorModels\Pages;

use App\Filament\Resources\LandingCalculatorModels\LandingCalculatorModelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLandingCalculatorModel extends EditRecord
{
    protected static string $resource = LandingCalculatorModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
