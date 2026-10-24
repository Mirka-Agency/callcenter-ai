<?php

namespace App\Filament\Resources\OrganizationCrmConnections;

use App\Filament\Concerns\AdminStaffOnly;
use App\Filament\Resources\OrganizationCrmConnections\Pages\CreateOrganizationCrmConnection;
use App\Filament\Resources\OrganizationCrmConnections\Pages\EditOrganizationCrmConnection;
use App\Filament\Resources\OrganizationCrmConnections\Pages\ListOrganizationCrmConnections;
use App\Filament\Resources\OrganizationCrmConnections\RelationManagers\ConnectionLogsRelationManager;
use App\Filament\Resources\OrganizationCrmConnections\RelationManagers\SyncLogsRelationManager;
use App\Filament\Resources\OrganizationCrmConnections\Schemas\OrganizationCrmConnectionForm;
use App\Filament\Resources\OrganizationCrmConnections\Tables\OrganizationCrmConnectionsTable;
use App\Models\OrganizationCrmConnection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class OrganizationCrmConnectionResource extends Resource
{
    use AdminStaffOnly;

    protected static ?string $model = OrganizationCrmConnection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.crm_connections');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.navigation.groups.crm');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['organization', 'provider']);
    }

    public static function form(Schema $schema): Schema
    {
        return OrganizationCrmConnectionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrganizationCrmConnectionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ConnectionLogsRelationManager::class,
            SyncLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrganizationCrmConnections::route('/'),
            'create' => CreateOrganizationCrmConnection::route('/create'),
            'edit' => EditOrganizationCrmConnection::route('/{record}/edit'),
        ];
    }
}
