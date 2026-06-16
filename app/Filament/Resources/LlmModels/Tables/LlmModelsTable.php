<?php

namespace App\Filament\Resources\LlmModels\Tables;

use App\Models\PlatformAiSettings;
use App\Services\AiCostEstimatorService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LlmModelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('provider.name')
                    ->label(__('filament.fields.provider'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('model_key')->badge()->searchable(),
                TextColumn::make('input_price_per_million_tokens')
                    ->label(__('filament.fields.input_per_1m'))
                    ->money(fn () => PlatformAiSettings::billingUnitCurrency())
                    ->sortable(),
                TextColumn::make('output_price_per_million_tokens')
                    ->label(__('filament.fields.output_per_1m'))
                    ->money(fn () => PlatformAiSettings::billingUnitCurrency())
                    ->sortable(),
                TextColumn::make('est_cost_per_minute')
                    ->label(__('filament.fields.est_per_min'))
                    ->getStateUsing(fn ($record) => app(AiCostEstimatorService::class)->costPerMinute($record))
                    ->formatStateUsing(fn ($state) => PlatformAiSettings::formatMoney($state))
                    ->sortable(false),
                TextColumn::make('est_cost_per_hour')
                    ->label(__('filament.fields.est_per_hour'))
                    ->getStateUsing(fn ($record) => app(AiCostEstimatorService::class)->costPerHour($record))
                    ->formatStateUsing(fn ($state) => PlatformAiSettings::formatMoney($state))
                    ->sortable(false),
                IconColumn::make('is_default')->boolean()->label(__('filament.fields.default')),
                IconColumn::make('is_active')->boolean()->label(__('filament.fields.active')),
            ])
            ->filters([
                SelectFilter::make('provider_id')
                    ->relationship('provider', 'name')
                    ->label(__('filament.fields.provider')),
                TernaryFilter::make('is_active')
                    ->label(__('filament.fields.active'))
                    ->nullable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }
}
