<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

class RequestArchiveActions
{
    /**
     * @return list<Action>
     */
    public static function recordActions(): array
    {
        return [
            self::archive(),
            self::restore(),
        ];
    }

    public static function archive(): Action
    {
        return Action::make('archive')
            ->label(__('filament.actions.archive'))
            ->icon('heroicon-o-archive-box')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(__('filament.actions.archive'))
            ->modalDescription(__('filament.misc.archive_request_description'))
            ->modalSubmitActionLabel(__('filament.actions.archive'))
            ->visible(fn (Model $record): bool => $record->archived_at === null)
            ->action(function (Model $record): void {
                $record->archive();

                Notification::make()
                    ->title(__('filament.notifications.request_archived'))
                    ->success()
                    ->send();
            });
    }

    public static function restore(): Action
    {
        return Action::make('restore')
            ->label(__('filament.actions.unarchive'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->visible(fn (Model $record): bool => $record->archived_at !== null)
            ->action(function (Model $record): void {
                $record->restoreFromArchive();

                Notification::make()
                    ->title(__('filament.notifications.request_restored'))
                    ->success()
                    ->send();
            });
    }

    public static function archivedAtColumn(): TextColumn
    {
        return TextColumn::make('archived_at')
            ->label(__('filament.fields.archived_at'))
            ->jalaliDateTime()
            ->sortable()
            ->visible(fn (mixed $livewire): bool => (bool) ($livewire->archived ?? false));
    }
}
