<?php

namespace App\Filament\Resources\LandingCalculatorModels\Tables;

use App\Support\PersianNumber;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LandingCalculatorModelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('cost_per_minute_tomans')
                    ->label(__('filament.fields.cost_per_minute_tomans'))
                    ->formatStateUsing(fn ($state) => (PersianNumber::format($state) ?? '۰').' تومان')
                    ->sortable(),
                TextColumn::make('position')
                    ->label(__('filament.fields.landing_calculator_position'))
                    ->sortable(),
                IconColumn::make('is_default')
                    ->label(__('filament.fields.default'))
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label(__('filament.fields.active'))
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('position');
    }
}
