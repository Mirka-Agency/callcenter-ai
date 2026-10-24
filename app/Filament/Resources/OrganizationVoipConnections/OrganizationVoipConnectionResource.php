<?php

namespace App\Filament\Resources\OrganizationVoipConnections;

use App\Filament\Concerns\AdminStaffOnly;
use App\Filament\Resources\OrganizationVoipConnections\Pages\CreateOrganizationVoipConnection;
use App\Filament\Resources\OrganizationVoipConnections\Pages\EditOrganizationVoipConnection;
use App\Filament\Resources\OrganizationVoipConnections\Pages\ListOrganizationVoipConnections;
use App\Filament\Resources\OrganizationVoipConnections\RelationManagers\CallLogsRelationManager;
use App\Filament\Resources\OrganizationVoipConnections\RelationManagers\SyncLogsRelationManager;
use App\Filament\Resources\OrganizationVoipConnections\RelationManagers\WebhookLogsRelationManager;
use App\Filament\Resources\OrganizationVoipConnections\Schemas\OrganizationVoipConnectionForm;
use App\Filament\Resources\OrganizationVoipConnections\Tables\OrganizationVoipConnectionsTable;
use App\Models\OrganizationVoipConnection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class OrganizationVoipConnectionResource extends Resource
{
    use AdminStaffOnly;

    protected static ?string $model = OrganizationVoipConnection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoneArrowUpRight;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.voip_connections');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.navigation.groups.voip');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['organization', 'provider']);
    }

    public static function form(Schema $schema): Schema
    {
        return OrganizationVoipConnectionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrganizationVoipConnectionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            CallLogsRelationManager::class,
            WebhookLogsRelationManager::class,
            SyncLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrganizationVoipConnections::route('/'),
            'create' => CreateOrganizationVoipConnection::route('/create'),
            'edit' => EditOrganizationVoipConnection::route('/{record}/edit'),
        ];
    }
}
