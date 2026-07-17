<?php

namespace App\Filament\Resources\OrganizationVoipConnections\Pages;

use App\Application\Voip\Services\VoipConnectionLifecycleService;
use App\Filament\Resources\OrganizationVoipConnections\OrganizationVoipConnectionResource;
use App\Models\OrganizationVoipConnection;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Throwable;

class EditOrganizationVoipConnection extends EditRecord
{
    protected static string $resource = OrganizationVoipConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testConnection')
                ->label(__('filament.actions.test_connection'))
                ->icon('heroicon-o-signal')
                ->color('info')
                ->action(function (OrganizationVoipConnection $record): void {
                    try {
                        $result = app(VoipConnectionLifecycleService::class)
                            ->test($record);

                        if ($result->success) {
                            Notification::make()
                                ->title(__('filament.notifications.connection_success'))
                                ->body($result->message ?? __('filament.notifications.voip_connection_working'))
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title(__('filament.notifications.connection_failed'))
                                ->body($result->error ?? __('filament.notifications.unable_connect_voip'))
                                ->danger()
                                ->send();
                        }
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->title(__('filament.notifications.connection_failed'))
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('syncExtensions')
                ->label(__('filament.actions.sync_extensions'))
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function (OrganizationVoipConnection $record): void {
                    app(VoipConnectionLifecycleService::class)
                        ->queueSyncExtensions($record);

                    Notification::make()
                        ->title(__('filament.notifications.sync_queued'))
                        ->body(__('filament.notifications.extension_sync_queued'))
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
