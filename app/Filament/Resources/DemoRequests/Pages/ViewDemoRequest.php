<?php

namespace App\Filament\Resources\DemoRequests\Pages;

use App\Filament\Resources\DemoRequests\DemoRequestResource;
use App\Filament\Support\RequestArchiveActions;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewDemoRequest extends ViewRecord
{
    protected static string $resource = DemoRequestResource::class;

    protected function getHeaderActions(): array
    {
        return RequestArchiveActions::recordActions();
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.models.demo_request'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('full_name')->label(__('filament.fields.full_name')),
                        TextEntry::make('phone')->label(__('filament.fields.contact_phone'))->copyable(),
                        TextEntry::make('business_domain')->label(__('filament.fields.business_domain')),
                        TextEntry::make('company_name')->label(__('filament.fields.company_name')),
                        TextEntry::make('website_url')
                            ->label(__('filament.fields.website_url'))
                            ->placeholder(__('filament.misc.em_dash'))
                            ->copyable()
                            ->columnSpanFull(),
                        TextEntry::make('created_at')
                            ->label(__('filament.fields.created_at'))
                            ->jalaliDateTime(),
                        TextEntry::make('archived_at')
                            ->label(__('filament.fields.archived_at'))
                            ->jalaliDateTime()
                            ->visible(fn ($record): bool => $record?->archived_at !== null),
                    ]),
            ]);
    }
}
