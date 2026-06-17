<?php

namespace App\Filament\Resources\LlmProviders\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LlmProvidersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('code')->badge()->searchable()->sortable(),
                TextColumn::make('models_count')
                    ->counts('models')
                    ->label(__('filament.fields.models'))
                    ->sortable(),
                IconColumn::make('api_key')
                    ->label(__('filament.fields.api_key'))
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->hasApiCredentials())
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),
                IconColumn::make('is_active')->boolean()->sortable(),
                TextColumn::make('created_at')->jalaliDateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('filament.fields.active'))
                    ->nullable()
                    ->trueLabel(__('filament.status.active'))
                    ->falseLabel(__('filament.status.inactive'))
                    ->queries(
                        true: fn (Builder $query) => $query->where('is_active', true),
                        false: fn (Builder $query) => $query->where('is_active', false),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription(__('filament.misc.delete_provider_warning')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }
}
