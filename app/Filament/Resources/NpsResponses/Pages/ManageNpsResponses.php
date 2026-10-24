<?php

namespace App\Filament\Resources\NpsResponses\Pages;

use App\Filament\Resources\NpsResponses\NpsResponseResource;
use App\Filament\Resources\NpsResponses\Widgets\NpsStats;
use Filament\Resources\Pages\ManageRecords;

class ManageNpsResponses extends ManageRecords
{
    protected static string $resource = NpsResponseResource::class;

    protected function getHeaderWidgets(): array
    {
        return [NpsStats::class];
    }
}
