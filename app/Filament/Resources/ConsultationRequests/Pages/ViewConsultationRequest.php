<?php

namespace App\Filament\Resources\ConsultationRequests\Pages;

use App\Filament\Resources\ConsultationRequests\ConsultationRequestResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewConsultationRequest extends ViewRecord
{
    protected static string $resource = ConsultationRequestResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.models.consultation_request'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('full_name')->label(__('filament.fields.full_name')),
                        TextEntry::make('phone')->label(__('filament.fields.contact_phone'))->copyable(),
                        TextEntry::make('company_name')->label(__('filament.fields.company_name')),
                        TextEntry::make('department')->label(__('filament.fields.department')),
                        TextEntry::make('notes')
                            ->label(__('filament.fields.description'))
                            ->placeholder(__('filament.misc.em_dash'))
                            ->columnSpanFull(),
                        TextEntry::make('created_at')
                            ->label(__('filament.fields.created_at'))
                            ->jalaliDateTime(),
                    ]),
            ]);
    }
}
