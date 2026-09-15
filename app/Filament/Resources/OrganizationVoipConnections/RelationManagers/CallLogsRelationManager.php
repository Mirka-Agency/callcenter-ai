<?php

namespace App\Filament\Resources\OrganizationVoipConnections\RelationManagers;

use App\Application\Voip\Support\VoipReportFilter;
use App\Domain\Voip\Enums\CallDirection;
use App\Domain\Voip\Enums\CallStatus;
use App\Filament\Resources\OrganizationVoipConnections\Tables\VoipReportTableFilters;
use App\Models\VoipCallLog;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CallLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'callLogs';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament.relation_managers.call_logs');
    }

    public function table(Table $table): Table
    {
        return VoipReportTableFilters::configure($table, VoipReportTableFilters::forCallLogs())
            ->columns([
                TextColumn::make('external_call_id')
                    ->label(__('filament.fields.call_id'))
                    ->searchable(),
                TextColumn::make('direction')
                    ->label(__('filament.fields.call_direction'))
                    ->badge()
                    ->formatStateUsing(fn (CallDirection $state): string => $state->label()),
                TextColumn::make('extension')
                    ->label(__('filament.fields.internal_number'))
                    ->state(fn (VoipCallLog $record): ?string => VoipReportFilter::extensionFromCallLog($record))
                    ->placeholder(__('filament.misc.em_dash')),
                TextColumn::make('source_number')
                    ->label(__('filament.fields.from'))
                    ->searchable(),
                TextColumn::make('destination_number')
                    ->label(__('filament.fields.to'))
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?CallStatus $state): string => $state?->label() ?? __('filament.misc.em_dash')),
                TextColumn::make('duration')
                    ->suffix('s')
                    ->placeholder(__('filament.misc.em_dash')),
                TextColumn::make('started_at')
                    ->jalaliDateTime()
                    ->sortable(),
            ])
            ->defaultSort('started_at', 'desc')
            ->paginated([10, 25, 50]);
    }
}
