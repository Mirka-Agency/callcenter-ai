<?php

namespace App\Filament\Resources\ConsultationRequests\Tables;

use App\Filament\Support\RequestArchiveActions;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConsultationRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label(__('filament.fields.full_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label(__('filament.fields.contact_phone'))
                    ->searchable(),
                TextColumn::make('company_name')
                    ->label(__('filament.fields.company_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('department')
                    ->label(__('filament.fields.department'))
                    ->searchable(),
                TextColumn::make('notes')
                    ->label(__('filament.fields.description'))
                    ->limit(80)
                    ->placeholder(__('filament.misc.em_dash'))
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
