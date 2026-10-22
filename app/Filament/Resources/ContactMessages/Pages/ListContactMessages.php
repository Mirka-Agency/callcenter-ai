<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Filament\Concerns\ListsArchivableRequests;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use Filament\Resources\Pages\ListRecords;

class ListContactMessages extends ListRecords
{
    use ListsArchivableRequests;

    protected static string $resource = ContactMessageResource::class;
}
