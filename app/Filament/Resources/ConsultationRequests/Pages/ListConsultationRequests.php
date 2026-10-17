<?php

namespace App\Filament\Resources\ConsultationRequests\Pages;

use App\Filament\Concerns\ListsArchivableRequests;
use App\Filament\Resources\ConsultationRequests\ConsultationRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListConsultationRequests extends ListRecords
{
    use ListsArchivableRequests;

    protected static string $resource = ConsultationRequestResource::class;
}
