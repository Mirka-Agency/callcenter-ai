<?php

namespace App\Filament\Resources\LlmModels\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

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
                IconColumn::make('is_default')->boolean()->label(__('filament.fields.platform_default')),
                IconColumn::make('is_transcription_default')->boolean()->label(__('filament.fields.transcription_default')),
                IconColumn::make('sends_audio_file')
                    ->boolean()
                    ->label(__('filament.fields.sends_audio_file'))
                    ->toggleable(),
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
