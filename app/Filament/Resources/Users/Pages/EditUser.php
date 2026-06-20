<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\Demo\DemoPersonProvisioner;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createDemo')
                ->label(__('filament.actions.create_demo_for_user'))
                ->icon(Heroicon::OutlinedBeaker)
                ->color('warning')
                ->visible(fn (): bool => auth()->user()?->role === UserRole::SuperAdmin
                    && $this->record instanceof User
                    && $this->record->role === UserRole::Employer)
                ->requiresConfirmation()
                ->modalHeading(__('filament.demo_import.create_for_user_heading'))
                ->modalDescription(fn (): string => __('filament.demo_import.create_for_user_description', [
                    'name' => $this->record->name,
                ]))
                ->modalSubmitActionLabel(__('filament.actions.create_demo_for_user'))
                ->action(function (): void {
                    /** @var User $user */
                    $user = $this->record;

                    $existing = $user->organizations()->where('is_demo', false)->first();

                    if ($existing !== null) {
                        Notification::make()
                            ->title(__('filament.demo_import.failed'))
                            ->body(__('filament.demo_import.user_has_real_org'))
                            ->danger()
                            ->send();

                        return;
                    }

                    try {
                        app(DemoPersonProvisioner::class)->provisionForUser($user);
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title(__('filament.demo_import.failed'))
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title(__('filament.demo_import.single_success'))
                        ->body(__('filament.demo_import.create_for_user_body', ['name' => $user->name]))
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
