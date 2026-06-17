<?php

namespace App\Filament\Resources\Organizations;

use App\Filament\Resources\Organizations\Pages\CreateOrganization;
use App\Filament\Resources\Organizations\Pages\EditOrganization;
use App\Filament\Resources\Organizations\Pages\ListOrganizations;
use App\Filament\Resources\Organizations\RelationManagers\CrmConnectionsRelationManager;
use App\Filament\Resources\Organizations\RelationManagers\EmployeeMembershipsRelationManager;
use App\Filament\Resources\Organizations\RelationManagers\EmployeesRelationManager;
use App\Filament\Resources\Organizations\RelationManagers\VoipConnectionsRelationManager;
use App\Filament\Resources\Organizations\RelationManagers\WalletTransactionsRelationManager;
use App\Filament\Resources\Organizations\Schemas\OrganizationForm;
use App\Filament\Resources\Organizations\Tables\OrganizationsTable;
use App\Models\Organization;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.organizations');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('filament.navigation.groups.organizations');
    }

    public static function getModelLabel(): string
    {
        return __('filament.models.organization');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.models.organizations');
    }

    public static function form(Schema $schema): Schema
    {
        return OrganizationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrganizationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            WalletTransactionsRelationManager::class,
            EmployeesRelationManager::class,
            EmployeeMembershipsRelationManager::class,
            CrmConnectionsRelationManager::class,
            VoipConnectionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrganizations::route('/'),
            'create' => CreateOrganization::route('/create'),
            'edit' => EditOrganization::route('/{record}/edit'),
        ];
    }
}
