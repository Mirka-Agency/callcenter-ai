<?php

namespace App\Filament\Resources\LandingCalculatorModels;

use App\Filament\Concerns\OnlySuperAdmin;
use App\Filament\Resources\LandingCalculatorModels\Pages\CreateLandingCalculatorModel;
use App\Filament\Resources\LandingCalculatorModels\Pages\EditLandingCalculatorModel;
use App\Filament\Resources\LandingCalculatorModels\Pages\ListLandingCalculatorModels;
use App\Filament\Resources\LandingCalculatorModels\Schemas\LandingCalculatorModelForm;
use App\Filament\Resources\LandingCalculatorModels\Tables\LandingCalculatorModelsTable;
use App\Models\LandingCalculatorModel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LandingCalculatorModelResource extends Resource
{
    use OnlySuperAdmin;

    protected static ?string $model = LandingCalculatorModel::class;

    protected static ?string $slug = 'landing-calculator-models';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.landing_calculator_models');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('filament.navigation.groups.organizations');
    }

    public static function getModelLabel(): string
    {
        return __('filament.models.landing_calculator_model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.models.landing_calculator_models');
    }

    public static function form(Schema $schema): Schema
    {
        return LandingCalculatorModelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LandingCalculatorModelsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLandingCalculatorModels::route('/'),
            'create' => CreateLandingCalculatorModel::route('/create'),
            'edit' => EditLandingCalculatorModel::route('/{record}/edit'),
        ];
    }
}
