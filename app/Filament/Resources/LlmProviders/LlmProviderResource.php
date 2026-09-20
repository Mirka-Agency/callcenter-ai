<?php

namespace App\Filament\Resources\LlmProviders;

use App\Filament\Concerns\OnlySuperAdmin;
use App\Filament\Resources\LlmProviders\Pages\CreateLlmProvider;
use App\Filament\Resources\LlmProviders\Pages\EditLlmProvider;
use App\Filament\Resources\LlmProviders\Pages\ListLlmProviders;
use App\Filament\Resources\LlmProviders\Schemas\LlmProviderForm;
use App\Filament\Resources\LlmProviders\Tables\LlmProvidersTable;
use App\Models\LlmProvider;
use App\Support\OnPrem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class LlmProviderResource extends Resource
{
    use OnlySuperAdmin;

    protected static ?string $model = LlmProvider::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.llm_providers');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return OnPrem::llmNavigationGroup();
    }

    public static function form(Schema $schema): Schema
    {
        return LlmProviderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LlmProvidersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLlmProviders::route('/'),
            'create' => CreateLlmProvider::route('/create'),
            'edit' => EditLlmProvider::route('/{record}/edit'),
        ];
    }
}
