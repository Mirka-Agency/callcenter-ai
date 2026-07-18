<?php

namespace App\Filament\Resources\PendingQueueJobs\Pages;

use App\Filament\Resources\PendingQueueJobs\PendingQueueJobResource;
use App\Models\PendingQueueJob;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPendingQueueJobs extends ListRecords
{
    protected static string $resource = PendingQueueJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('flush_pending')
                ->label(__('filament.actions.flush_pending_jobs'))
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription(__('filament.misc.flush_pending_jobs_description'))
                ->visible(fn (): bool => PendingQueueJob::query()->exists())
                ->action(function (): void {
                    $count = PendingQueueJob::query()->count();
                    PendingQueueJob::query()->delete();

                    Notification::make()
                        ->title(__('filament.notifications.pending_jobs_flushed'))
                        ->body(__('filament.notifications.pending_queue_jobs_deleted', ['count' => $count]))
                        ->success()
                        ->send();
                }),
        ];
    }
}
