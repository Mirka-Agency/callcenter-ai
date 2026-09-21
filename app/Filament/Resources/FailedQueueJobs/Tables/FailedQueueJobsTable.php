<?php

namespace App\Filament\Resources\FailedQueueJobs\Tables;

use App\Models\FailedQueueJob;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;

class FailedQueueJobsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('job_class')
                    ->label(__('filament.fields.job_class'))
                    ->getStateUsing(fn (FailedQueueJob $record) => $record->jobClassLabel())
                    ->searchable(query: function ($query, string $search) {
                        $query->where('payload', 'like', '%'.$search.'%');
                    })
                    ->sortable(false),
                TextColumn::make('call_id')
                    ->label(__('filament.fields.call_id'))
                    ->getStateUsing(fn (FailedQueueJob $record) => $record->callId())
                    ->placeholder(__('filament.misc.em_dash')),
                TextColumn::make('queue')
                    ->badge()
                    ->sortable(),
                TextColumn::make('connection')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('failure_reason')
                    ->label(__('filament.fields.failure_reason'))
                    ->getStateUsing(fn (FailedQueueJob $record) => $record->failureReason())
                    ->limit(120)
                    ->tooltip(fn (FailedQueueJob $record) => $record->failureReason())
                    ->wrap()
                    ->color('danger')
                    ->searchable(query: function ($query, string $search) {
                        $query->where('exception', 'like', '%'.$search.'%');
                    }),
                TextColumn::make('failed_at')
                    ->jalaliDateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('queue')
                    ->options(fn () => FailedQueueJob::query()->distinct()->orderBy('queue')->pluck('queue', 'queue')->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('retry')
                    ->label(__('filament.actions.retry_job'))
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->action(function (FailedQueueJob $record): void {
                        Artisan::call('queue:retry', ['id' => $record->uuid]);

                        Notification::make()
                            ->title(__('filament.notifications.queue_job_retried'))
                            ->success()
                            ->send();
                    }),
                Action::make('delete')
                    ->label(__('filament.actions.delete'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (FailedQueueJob $record): void {
                        app('queue.failer')->forget($record->uuid);

                        Notification::make()
                            ->title(__('filament.notifications.queue_job_deleted'))
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('failed_at', 'desc')
            ->paginated([25, 50, 100]);
    }
}
