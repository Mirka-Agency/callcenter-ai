<?php

namespace App\Filament\Resources\FailedQueueJobs\Pages;

use App\Filament\Resources\FailedQueueJobs\FailedQueueJobResource;
use App\Models\FailedQueueJob;
use Filament\Actions\Action;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Artisan;

class ViewFailedQueueJob extends ViewRecord
{
    protected static string $resource = FailedQueueJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
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

                    $this->redirect(FailedQueueJobResource::getUrl('index'));
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

                    $this->redirect(FailedQueueJobResource::getUrl('index'));
                }),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        /** @var FailedQueueJob $record */
        $record = $this->getRecord();
        $inspection = $record->inspection();

        return $schema
            ->components([
                Section::make(__('filament.sections.job_summary'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('uuid')->label(__('filament.fields.job_uuid'))->copyable(),
                        TextEntry::make('job_class')
                            ->label(__('filament.fields.job_class'))
                            ->state($inspection->shortLabel()),
                        TextEntry::make('queue')->badge(),
                        TextEntry::make('connection'),
                        TextEntry::make('failed_at')->jalaliDateTime(),
                        TextEntry::make('max_tries')
                            ->label(__('filament.fields.max_tries'))
                            ->state($inspection->maxTries)
                            ->placeholder(__('filament.misc.em_dash')),
                        TextEntry::make('timeout')
                            ->label(__('filament.fields.timeout_seconds'))
                            ->state($inspection->timeout)
                            ->placeholder(__('filament.misc.em_dash')),
                        TextEntry::make('call_id')
                            ->label(__('filament.fields.call_id'))
                            ->state($inspection->callId())
                            ->placeholder(__('filament.misc.em_dash')),
                    ]),
                Section::make(__('filament.sections.job_payload'))
                    ->description(__('filament.misc.queue_job_payload_description'))
                    ->schema([
                        KeyValueEntry::make('job_properties')
                            ->label(__('filament.fields.sent_data'))
                            ->state($inspection->propertiesForDisplay())
                            ->columnSpanFull(),
                        TextEntry::make('chained_jobs')
                            ->label(__('filament.fields.chained_jobs'))
                            ->state($inspection->chainedJobs !== [] ? implode("\n", $inspection->chainedJobs) : null)
                            ->placeholder(__('filament.misc.em_dash'))
                            ->columnSpanFull()
                            ->markdown(),
                    ])
                    ->visible($inspection->properties !== [] || $inspection->chainedJobs !== []),
                Section::make(__('filament.sections.error_details'))
                    ->schema([
                        TextEntry::make('failure_reason')
                            ->label(__('filament.fields.failure_reason'))
                            ->state($inspection->failureReason ?: $inspection->exceptionMessage)
                            ->color('danger')
                            ->columnSpanFull(),
                        TextEntry::make('exception')
                            ->label(__('filament.fields.stack_trace'))
                            ->state($inspection->exceptionFull)
                            ->fontFamily('mono')
                            ->size('sm')
                            ->columnSpanFull()
                            ->markdown(),
                    ]),
                Section::make(__('filament.sections.raw_payload'))
                    ->collapsed()
                    ->schema([
                        TextEntry::make('payload')
                            ->label(__('filament.fields.raw_payload'))
                            ->formatStateUsing(fn (string $state): string => json_encode(
                                json_decode($state, true),
                                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                            ) ?: $state)
                            ->fontFamily('mono')
                            ->size('sm')
                            ->columnSpanFull()
                            ->markdown(),
                    ]),
            ]);
    }
}
