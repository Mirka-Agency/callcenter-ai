<?php

namespace App\Filament\Resources\ConversationAnalyses\Tables;

use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Models\ConversationAnalysis;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ConversationAnalysesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('analyzed_at')
                    ->label(__('filament.fields.call_date'))
                    ->jalaliDateTime()
                    ->sortable(),
                TextColumn::make('source')
                    ->label(__('filament.fields.source'))
                    ->badge()
                    ->formatStateUsing(fn (ConversationSource $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('organization.title')
                    ->label(__('filament.fields.organization'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employee.full_name')
                    ->label(__('filament.fields.employee'))
                    ->searchable()
                    ->placeholder(__('filament.misc.em_dash')),
                TextColumn::make('sentiment')
                    ->label(__('filament.fields.sentiment'))
                    ->badge()
                    ->formatStateUsing(fn (?AnalysisSentiment $state): ?string => $state?->label())
                    ->color(fn (?AnalysisSentiment $state): string|array|null => $state?->color())
                    ->toggleable(),
                TextColumn::make('model_name')
                    ->label(__('filament.fields.model'))
                    ->badge()
                    ->searchable(),
                TextColumn::make('llm_provider')
                    ->label(__('filament.fields.provider'))
                    ->toggleable(),
                TextColumn::make('processing_duration_ms')
                    ->label(__('filament.fields.processing_ms'))
                    ->sortable(),
                TextColumn::make('score')
                    ->label(__('filament.fields.score'))
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 85 => 'success',
                        $state >= 70 => 'warning',
                        default => 'danger',
                    }),
            ])
            ->filters([
                SelectFilter::make('source')
                    ->label(__('filament.fields.source'))
                    ->options(ConversationSource::options()),
                SelectFilter::make('organization_id')
                    ->label(__('filament.fields.organization'))
                    ->relationship('organization', 'title')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('organization_user_id')
                    ->label(__('filament.fields.employee'))
                    ->relationship('employee', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                    ->searchable()
                    ->preload(),
                SelectFilter::make('sentiment')
                    ->label(__('filament.fields.sentiment'))
                    ->options(collect(AnalysisSentiment::cases())
                        ->mapWithKeys(fn (AnalysisSentiment $sentiment) => [$sentiment->value => $sentiment->label()])
                        ->all()),
                SelectFilter::make('llm_provider')
                    ->label(__('filament.fields.provider'))
                    ->options(fn () => ConversationAnalysis::query()
                        ->distinct()
                        ->pluck('llm_provider', 'llm_provider')
                        ->all()),
                SelectFilter::make('model_name')
                    ->label(__('filament.fields.model'))
                    ->options(fn () => ConversationAnalysis::query()
                        ->distinct()
                        ->pluck('model_name', 'model_name')
                        ->all()),
                Filter::make('score_range')
                    ->label(__('filament.fields.score'))
                    ->schema([
                        TextInput::make('min')->persianNumeric(0)->label(__('filament.fields.min_score')),
                        TextInput::make('max')->persianNumeric(0)->label(__('filament.fields.max_score')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['min'], fn (Builder $q, $min) => $q->where('score', '>=', $min))
                            ->when($data['max'], fn (Builder $q, $max) => $q->where('score', '<=', $max));
                    }),
                Filter::make('analyzed_between')
                    ->label(__('filament.fields.call_date'))
                    ->schema([
                        DatePicker::make('from')->jalali()->label(__('filament.fields.from')),
                        DatePicker::make('until')->jalali()->label(__('filament.fields.until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('analyzed_at', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('analyzed_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make()->label(__('filament.actions.view')),
            ])
            ->defaultSort('analyzed_at', 'desc')
            ->paginated([25, 50, 100]);
    }
}
