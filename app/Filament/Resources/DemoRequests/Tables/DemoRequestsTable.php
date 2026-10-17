<?php

namespace App\Filament\Resources\DemoRequests\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DemoRequestsTable
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
                TextColumn::make('business_domain')
                    ->label(__('filament.fields.business_domain'))
                    ->searchable(),
                TextColumn::make('company_name')
                    ->label(__('filament.fields.company_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('website_url')
                    ->label(__('filament.fields.website_url'))
                    ->placeholder(__('filament.misc.em_dash'))
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('filament.fields.created_at'))
                    ->jalaliDateTime()
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100]);
    }
}
