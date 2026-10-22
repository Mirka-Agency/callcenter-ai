<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Enums\ContactMessageSubject;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Support\RequestArchiveActions;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewContactMessage extends ViewRecord
{
    protected static string $resource = ContactMessageResource::class;

    protected function getHeaderActions(): array
    {
        return RequestArchiveActions::recordActions();
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.models.contact_message'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('full_name')->label(__('filament.fields.full_name')),
                        TextEntry::make('email')->label(__('filament.fields.email'))->copyable(),
                        TextEntry::make('subject')
                            ->label(__('filament.fields.subject'))
                            ->formatStateUsing(fn (mixed $state): string => ContactMessageSubject::labelOf($state)),
                        TextEntry::make('created_at')
                            ->label(__('filament.fields.created_at'))
                            ->jalaliDateTime(),
                        TextEntry::make('message')
                            ->label(__('filament.fields.message'))
                            ->columnSpanFull(),
                        TextEntry::make('archived_at')
                            ->label(__('filament.fields.archived_at'))
                            ->jalaliDateTime()
                            ->visible(fn ($record): bool => $record?->archived_at !== null),
                    ]),
            ]);
    }
}
