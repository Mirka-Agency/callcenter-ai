<?php

namespace App\Filament\Resources\MarketingSpends;

use App\Domain\Platform\Enums\AdminArea;
use App\Filament\Concerns\RequiresAdminArea;
use App\Filament\Resources\MarketingSpends\Pages\ManageMarketingSpends;
use App\Filament\Support\MoneyInput;
use App\Models\DemoRequest;
use App\Models\MarketingSpend;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MarketingSpendResource extends Resource
{
    use RequiresAdminArea;

    protected static ?string $model = MarketingSpend::class;

    protected static ?string $slug = 'marketing-spends';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?int $navigationSort = 8;

    public static function adminArea(): AdminArea
    {
        return AdminArea::Finance;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.marketing_spends');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('filament.navigation.groups.finance');
    }

    public static function getModelLabel(): string
    {
        return __('admin.models.marketing_spend');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.navigation.marketing_spends');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            DatePicker::make('spent_on')->label(__('admin.billing.spent_on'))->jalali()->default(now())->required(),
            Select::make('channel')->label(__('admin.billing.channel'))
                ->helperText(__('admin.billing.channel_help'))
                ->options(fn (): array => DemoRequest::query()->whereNotNull('source')->distinct()->pluck('source', 'source')
                    ->union(MarketingSpend::query()->distinct()->pluck('channel', 'channel'))->all())
                ->searchable()->required()->native(false)
                ->createOptionForm([TextInput::make('channel')->required()->maxLength(100)])
                ->createOptionUsing(fn (array $data): string => $data['channel']),
            MoneyInput::make('amount_rials')->label(__('admin.billing.amount'))->required(),
            Textarea::make('notes')->label(__('admin.billing.notes'))->rows(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('spent_on')->label(__('admin.billing.spent_on'))->jalaliDate()->sortable(),
                TextColumn::make('channel')->label(__('admin.billing.channel'))->badge()->searchable(),
                MoneyInput::column('amount_rials')->label(__('admin.billing.amount'))->sortable(),
                TextColumn::make('notes')->label(__('admin.billing.notes'))->limit(60)->toggleable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('spent_on', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMarketingSpends::route('/'),
        ];
    }
}
