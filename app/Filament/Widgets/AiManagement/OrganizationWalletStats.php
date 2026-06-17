<?php

namespace App\Filament\Widgets\AiManagement;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\PlatformAiSettings;
use App\Services\WalletService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OrganizationWalletStats extends StatsOverviewWidget
{
    public ?int $organizationId = null;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->role === UserRole::SuperAdmin;
    }

    protected function getStats(): array
    {
        if (! $this->organizationId) {
            return [];
        }

        $organization = Organization::query()->with('wallet')->find($this->organizationId);

        if (! $organization) {
            return [];
        }

        $wallet = app(WalletService::class)->forOrganization($organization->id);
        $wallet->refresh();

        return [
            Stat::make(__('filament.fields.balance'), PlatformAiSettings::formatMoney((float) $wallet->balance))
                ->description(__('filament.navigation.wallet').' · '.$wallet->currency),
            Stat::make(__('filament.fields.wallet_currency'), $wallet->currency)
                ->description(__('filament.misc.wallet_currency_note')),
        ];
    }
}
