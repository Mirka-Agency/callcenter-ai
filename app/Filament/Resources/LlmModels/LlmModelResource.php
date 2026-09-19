<?php

namespace App\Filament\Resources\LlmModels;

use App\Filament\Concerns\OnlySuperAdmin;
use App\Filament\Resources\LlmModels\Pages\CostEstimator;
use App\Filament\Resources\LlmModels\Pages\CreateLlmModel;
use App\Filament\Resources\LlmModels\Pages\EditLlmModel;
use App\Filament\Resources\LlmModels\Pages\ListLlmModels;
use App\Filament\Resources\LlmModels\Schemas\LlmModelForm;
use App\Filament\Resources\LlmModels\Tables\LlmModelsTable;
use App\Models\LlmModel;
use App\Support\OnPrem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class LlmModelResource extends Resource
{
    use OnlySuperAdmin;

    protected static ?string $model = LlmModel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.llm_models');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return OnPrem::llmNavigationGroup();
    }

    public static function form(Schema $schema): Schema
    {
        return LlmModelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LlmModelsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLlmModels::route('/'),
            'cost-estimator' => CostEstimator::route('/cost-estimator'),
            'create' => CreateLlmModel::route('/create'),
            'edit' => EditLlmModel::route('/{record}/edit'),
        ];
    }
}
