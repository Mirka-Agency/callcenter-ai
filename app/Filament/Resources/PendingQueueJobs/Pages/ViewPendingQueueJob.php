<?php

namespace App\Filament\Resources\PendingQueueJobs\Pages;

use App\Filament\Resources\PendingQueueJobs\PendingQueueJobResource;
use App\Models\PendingQueueJob;
use Filament\Actions\Action;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewPendingQueueJob extends ViewRecord
{
    protected static string $resource = PendingQueueJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('delete')
                ->label(__('filament.actions.delete'))
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (PendingQueueJob $record): void {
                    $record->delete();

                    Notification::make()
                        ->title(__('filament.notifications.pending_queue_job_deleted'))
                        ->success()
                        ->send();

                    $this->redirect(PendingQueueJobResource::getUrl('index'));
                }),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        /** @var PendingQueueJob $record */
        $record = $this->getRecord();
        $inspection = $record->inspection();

        return $schema
            ->components([
                Section::make(__('filament.sections.job_summary'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('id')->label(__('filament.fields.id')),
                        TextEntry::make('job_class')
                            ->label(__('filament.fields.job_class'))
                            ->state($inspection->shortLabel()),
                        TextEntry::make('queue')->badge(),
                        TextEntry::make('attempts'),
                        TextEntry::make('status')
                            ->label(__('filament.fields.status'))
                            ->state($record->isReserved()
                                ? __('filament.status.processing')
                                : __('filament.status.queued'))
                            ->badge(),
                        TextEntry::make('queued_at')
                            ->label(__('filament.fields.queued_at'))
                            ->state($record->queuedAt())
                            ->jalaliDateTime(),
                        TextEntry::make('call_id')
                            ->label(__('filament.fields.call_id'))
                            ->state($inspection->callId())
                            ->placeholder(__('filament.misc.em_dash')),
                    ]),
                Section::make(__('filament.sections.job_payload'))
                    ->schema([
                        KeyValueEntry::make('job_properties')
                            ->label(__('filament.fields.sent_data'))
                            ->state($inspection->propertiesForDisplay())
                            ->columnSpanFull(),
                        TextEntry::make('chained_jobs')
                            ->label(__('filament.fields.chained_jobs'))
                            ->state($inspection->chainedJobs !== [] ? implode("\n", $inspection->chainedJobs) : null)
                            ->placeholder(__('filament.misc.em_dash'))
                            ->columnSpanFull(),
                    ]),
                Section::make(__('filament.sections.raw_payload'))
                    ->collapsed()
                    ->schema([
                        TextEntry::make('payload')
                            ->formatStateUsing(fn (string $state): string => json_encode(
                                json_decode($state, true),
                                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                            ) ?: $state)
                            ->fontFamily('mono')
                            ->size('sm')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
