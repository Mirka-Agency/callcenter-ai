<?php

namespace App\Filament\Resources\AiCostRates;

use App\Domain\Platform\Enums\AdminArea;
use App\Filament\Concerns\RequiresAdminArea;
use App\Filament\Resources\AiCostRates\Pages\ManageAiCostRates;
use App\Filament\Support\MoneyInput;
use App\Models\AiCostRate;
use App\Models\ConversationAnalysis;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AiCostRateResource extends Resource
{
    use RequiresAdminArea;

    protected static ?string $model = AiCostRate::class;

    protected static ?string $slug = 'ai-cost-rates';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?int $navigationSort = 7;

    public static function adminArea(): AdminArea
    {
        return AdminArea::Finance;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.ai_cost_rates');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('filament.navigation.groups.finance');
    }

    public static function getModelLabel(): string
    {
        return __('admin.models.ai_cost_rate');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.navigation.ai_cost_rates');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('model_name')->label(__('admin.operations.model'))
                ->options(fn (): array => ConversationAnalysis::query()->distinct()->orderBy('model_name')->pluck('model_name', 'model_name')->all())
                ->searchable()->required()->native(false)
                ->createOptionForm([TextInput::make('model_name')->required()])
                ->createOptionUsing(fn (array $data): string => $data['model_name']),
            MoneyInput::make('input_cost_per_million_rials')->label(__('admin.billing.input_cost_per_million'))->required(),
            MoneyInput::make('output_cost_per_million_rials')->label(__('admin.billing.output_cost_per_million'))->required(),
            DatePicker::make('effective_from')->label(__('admin.billing.effective_from'))->jalali()->default(now())->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('model_name')->label(__('admin.operations.model'))->searchable(),
                MoneyInput::column('input_cost_per_million_rials')->label(__('admin.billing.input_cost_per_million')),
                MoneyInput::column('output_cost_per_million_rials')->label(__('admin.billing.output_cost_per_million')),
                TextColumn::make('effective_from')->label(__('admin.billing.effective_from'))->jalaliDate()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('effective_from', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAiCostRates::route('/'),
        ];
    }
}
