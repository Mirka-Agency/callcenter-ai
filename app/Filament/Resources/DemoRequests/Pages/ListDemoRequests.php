<?php

namespace App\Filament\Resources\DemoRequests\Pages;

use App\Filament\Concerns\ListsArchivableRequests;
use App\Filament\Resources\DemoRequests\DemoRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListDemoRequests extends ListRecords
{
    use ListsArchivableRequests;

    protected static string $resource = DemoRequestResource::class;
}
