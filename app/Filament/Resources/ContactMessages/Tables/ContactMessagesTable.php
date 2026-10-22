<?php

namespace App\Filament\Resources\ContactMessages\Tables;

use App\Enums\ContactMessageSubject;
use App\Filament\Support\RequestArchiveActions;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContactMessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label(__('filament.fields.full_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('filament.fields.email'))
                    ->searchable(),
                TextColumn::make('subject')
                    ->label(__('filament.fields.subject'))
                    ->formatStateUsing(fn (mixed $state): string => ContactMessageSubject::labelOf($state)),
                TextColumn::make('message')
                    ->label(__('filament.fields.message'))
                    ->limit(80)
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('filament.fields.created_at'))
                    ->jalaliDateTime()
                    ->sortable(),
                RequestArchiveActions::archivedAtColumn(),
            ])
            ->recordActions([
                ViewAction::make(),
                ...RequestArchiveActions::recordActions(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100]);
    }
}
