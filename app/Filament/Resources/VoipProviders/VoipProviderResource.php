<?php

namespace App\Filament\Resources\VoipProviders;

use App\Filament\Concerns\AdminStaffOnly;
use App\Filament\Resources\VoipProviders\Pages\CreateVoipProvider;
use App\Filament\Resources\VoipProviders\Pages\EditVoipProvider;
use App\Filament\Resources\VoipProviders\Pages\ListVoipProviders;
use App\Filament\Resources\VoipProviders\Schemas\VoipProviderForm;
use App\Filament\Resources\VoipProviders\Tables\VoipProvidersTable;
use App\Models\VoipProvider;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class VoipProviderResource extends Resource
{
    use AdminStaffOnly;

    protected static ?string $model = VoipProvider::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhone;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.voip_providers');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.navigation.groups.voip');
    }

    public static function form(Schema $schema): Schema
    {
        return VoipProviderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VoipProvidersTable::configure($table);
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
            'index' => ListVoipProviders::route('/'),
            'create' => CreateVoipProvider::route('/create'),
            'edit' => EditVoipProvider::route('/{record}/edit'),
        ];
    }
}
