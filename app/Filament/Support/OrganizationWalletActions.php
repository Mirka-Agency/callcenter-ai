<?php

namespace App\Filament\Support;

use App\Models\Organization;
use App\Services\WalletService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

final class OrganizationWalletActions
{
    public static function addCredits(): Action
    {
        return Action::make('addCredits')
            ->label(__('filament.actions.add_credits'))
            ->icon('heroicon-o-plus-circle')
            ->color('success')
            ->form(self::formSchema())
            ->action(function (array $data, EditRecord $livewire): void {
                $organization = $livewire->getRecord();

                if (! $organization instanceof Organization) {
                    return;
                }

                app(WalletService::class)->deposit(
                    $organization->id,
                    (float) $data['amount'],
                    $data['description'] ?? null,
                );

                Notification::make()
                    ->title(__('filament.notifications.credits_added'))
                    ->success()
                    ->send();
            });
    }

    public static function deductCredits(): Action
    {
        return Action::make('deductCredits')
            ->label(__('filament.actions.deduct_credits'))
            ->icon('heroicon-o-minus-circle')
            ->color('danger')
            ->form(self::formSchema())
            ->action(function (array $data, EditRecord $livewire): void {
                $organization = $livewire->getRecord();

                if (! $organization instanceof Organization) {
                    return;
                }

                app(WalletService::class)->withdraw(
                    $organization->id,
                    (float) $data['amount'],
                    $data['description'] ?? null,
                );

                Notification::make()
                    ->title(__('filament.notifications.credits_deducted'))
                    ->success()
                    ->send();
            });
    }

    /** @return array<int, TextInput|Textarea> */
    private static function formSchema(): array
    {
        return [
            TextInput::make('amount')
                ->persianNumeric(0)
                ->required()
                ->minValue(1)
                ->step(1),
            Textarea::make('description')
                ->rows(2),
        ];
    }
}
