<?php

namespace App\Filament\Resources\OrganizationCrmConnections\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrganizationCrmConnectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('organization.title')
                    ->label(__('filament.fields.organization'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('provider.name')
                    ->label(__('filament.fields.crm_provider'))
                    ->badge()
                    ->sortable(),
                IconColumn::make('is_default')
                    ->label(__('filament.fields.default'))
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('filament.fields.crm_enabled_for_org'))
                    ->boolean()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->jalaliDateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('organization_id')
                    ->label(__('filament.fields.organization'))
                    ->relationship('organization', 'title')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('crm_provider_id')
                    ->label(__('filament.fields.crm_provider'))
                    ->relationship('provider', 'name')
                    ->searchable()
                    ->preload(),
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
                TernaryFilter::make('is_default')
                    ->label(__('filament.fields.default'))
                    ->nullable()
                    ->trueLabel(__('filament.fields.default'))
                    ->falseLabel(__('filament.fields.not_default'))
                    ->queries(
                        true: fn (Builder $query) => $query->where('is_default', true),
                        false: fn (Builder $query) => $query->where('is_default', false),
                        blank: fn (Builder $query) => $query,
                    ),
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
