<?php

namespace App\Filament\Resources\ConsultationRequests\Pages;

use App\Filament\Resources\ConsultationRequests\ConsultationRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListConsultationRequests extends ListRecords
{
    protected static string $resource = ConsultationRequestResource::class;
}
