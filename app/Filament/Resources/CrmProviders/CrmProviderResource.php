<?php

namespace App\Filament\Resources\CrmProviders;

use App\Filament\Concerns\AdminStaffOnly;
use App\Filament\Resources\CrmProviders\Pages\CreateCrmProvider;
use App\Filament\Resources\CrmProviders\Pages\EditCrmProvider;
use App\Filament\Resources\CrmProviders\Pages\ListCrmProviders;
use App\Filament\Resources\CrmProviders\Schemas\CrmProviderForm;
use App\Filament\Resources\CrmProviders\Tables\CrmProvidersTable;
use App\Models\CrmProvider;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CrmProviderResource extends Resource
{
    use AdminStaffOnly;

    protected static ?string $model = CrmProvider::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCloud;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.crm_providers');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.navigation.groups.crm');
    }

    public static function form(Schema $schema): Schema
    {
        return CrmProviderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CrmProvidersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\MetaDefinitionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCrmProviders::route('/'),
            'create' => CreateCrmProvider::route('/create'),
            'edit' => EditCrmProvider::route('/{record}/edit'),
        ];
    }
}
