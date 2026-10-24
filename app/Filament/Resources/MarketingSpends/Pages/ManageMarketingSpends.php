<?php

namespace App\Filament\Resources\MarketingSpends\Pages;

use App\Filament\Resources\MarketingSpends\MarketingSpendResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageMarketingSpends extends ManageRecords
{
    protected static string $resource = MarketingSpendResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
