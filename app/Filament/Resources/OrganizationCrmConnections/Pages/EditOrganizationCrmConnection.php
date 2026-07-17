<?php

namespace App\Filament\Resources\OrganizationCrmConnections\Pages;

use App\Application\Crm\Services\CrmConnectionLifecycleService;
use App\Filament\Resources\OrganizationCrmConnections\OrganizationCrmConnectionResource;
use App\Models\OrganizationCrmConnection;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Throwable;

class EditOrganizationCrmConnection extends EditRecord
{
    protected static string $resource = OrganizationCrmConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testConnection')
                ->label(__('filament.actions.test_connection'))
                ->icon('heroicon-o-signal')
                ->color('info')
                ->action(function (OrganizationCrmConnection $record): void {
                    try {
                        $result = app(CrmConnectionLifecycleService::class)
                            ->test($record);

                        if ($result->success) {
                            Notification::make()
                                ->title(__('filament.notifications.connection_success'))
                                ->body($result->message ?? __('filament.notifications.crm_connection_working'))
                                ->success()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title(__('filament.notifications.connection_failed'))
                            ->body($result->error ?? __('filament.notifications.unable_connect_crm'))
                            ->danger()
                            ->send();
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->title(__('filament.notifications.connection_failed'))
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('syncNow')
                ->label(__('filament.actions.sync_now'))
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function (OrganizationCrmConnection $record): void {
                    app(CrmConnectionLifecycleService::class)
                        ->queueSync($record);

                    Notification::make()
                        ->title(__('filament.notifications.sync_queued'))
                        ->body(__('filament.notifications.crm_sync_queued'))
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
