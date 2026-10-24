<?php

namespace App\Filament\Resources\Organizations;

use App\Filament\Concerns\AdminStaffOnly;
use App\Filament\Resources\Organizations\Pages\CreateOrganization;
use App\Filament\Resources\Organizations\Pages\EditOrganization;
use App\Filament\Resources\Organizations\Pages\ListOrganizations;
use App\Filament\Resources\Organizations\Pages\OrganizationOverview;
use App\Filament\Resources\Organizations\RelationManagers\CrmConnectionsRelationManager;
use App\Filament\Resources\Organizations\RelationManagers\EmployeeMembershipsRelationManager;
use App\Filament\Resources\Organizations\RelationManagers\EmployeesRelationManager;
use App\Filament\Resources\Organizations\RelationManagers\VoipConnectionsRelationManager;
use App\Filament\Resources\Organizations\Schemas\OrganizationForm;
use App\Filament\Resources\Organizations\Tables\OrganizationsTable;
use App\Models\Organization;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class OrganizationResource extends Resource
{
    use AdminStaffOnly;

    protected static ?string $model = Organization::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $recordTitleAttribute = 'title';

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'legal_name', 'national_id', 'employer.name', 'employer.email', 'employer.phone'];
    }

    public static function getGlobalSearchResultUrl(Model $record): string
    {
        return static::getUrl('overview', ['record' => $record]);
    }

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
            'overview' => OrganizationOverview::route('/{record}/overview'),
            'edit' => EditOrganization::route('/{record}/edit'),
        ];
    }
}
