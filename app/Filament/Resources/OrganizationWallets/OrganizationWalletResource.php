<?php

namespace App\Filament\Resources\OrganizationWallets;

use App\Filament\Concerns\OnlySuperAdmin;
use App\Filament\Resources\OrganizationWallets\Pages\ListOrganizationWallets;
use App\Filament\Resources\OrganizationWallets\Pages\ViewOrganizationWallet;
use App\Filament\Resources\OrganizationWallets\RelationManagers\WalletTransactionsRelationManager;
use App\Filament\Resources\OrganizationWallets\Tables\OrganizationWalletsTable;
use App\Models\OrganizationWallet;
use App\Support\OnPrem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class OrganizationWalletResource extends Resource
{
    use OnlySuperAdmin;

    protected static ?string $model = OrganizationWallet::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'organization.title';

    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.organization_wallets');
    }

    protected static function isAvailableInCurrentDeployment(): bool
    {
        return ! OnPrem::billingHidden();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.navigation.groups.ai_billing');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return OrganizationWalletsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            WalletTransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrganizationWallets::route('/'),
            'view' => ViewOrganizationWallet::route('/{record}'),
        ];
    }
}
