<?php

namespace App\Filament\Resources\OrganizationVoipConnections\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrganizationVoipConnectionsTable
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
                    ->label(__('filament.fields.voip_provider'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('inbound_webhook_url')
                    ->label(__('filament.fields.voip_inbound_webhook_url'))
                    ->copyable()
                    ->limit(48)
                    ->tooltip(fn ($record) => $record->inbound_webhook_url)
                    ->toggleable(),
                TextColumn::make('call_logs_count')
                    ->counts('callLogs')
                    ->label(__('filament.fields.calls'))
                    ->sortable(),
                IconColumn::make('is_default')
                    ->label(__('filament.fields.default'))
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('filament.fields.active'))
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('organization_id')
                    ->label(__('filament.fields.organization'))
                    ->relationship('organization', 'title')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('voip_provider_id')
                    ->label(__('filament.fields.voip_provider'))
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
