<?php

namespace App\Filament\Resources\Plans;

use App\Domain\Platform\Enums\AdminArea;
use App\Domain\Platform\Enums\BillingPeriod;
use App\Filament\Concerns\RequiresAdminArea;
use App\Filament\Resources\Plans\Pages\ManagePlans;
use App\Filament\Support\MoneyInput;
use App\Models\Plan;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlanResource extends Resource
{
    use RequiresAdminArea;

    protected static ?string $model = Plan::class;

    protected static ?string $slug = 'plans';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 1;

    public static function adminArea(): AdminArea
    {
        return AdminArea::Finance;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.plans');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('filament.navigation.groups.finance');
    }

    public static function getModelLabel(): string
    {
        return __('admin.models.plan');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.navigation.plans');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label(__('admin.billing.plan_name'))->required()->maxLength(255),
            TextInput::make('code')->label(__('admin.billing.code'))->required()->alphaDash()->maxLength(50)->unique(ignoreRecord: true),
            MoneyInput::make('price_rials')->label(__('admin.billing.price'))->required(),
            Select::make('billing_period')->label(__('admin.billing.billing_period'))->options(BillingPeriod::options())->default(BillingPeriod::Monthly->value)->required()->native(false),
            TextInput::make('call_quota')->label(__('admin.billing.call_quota'))->helperText(__('admin.billing.call_quota_help'))->numeric()->minValue(1),
            TextInput::make('seat_limit')->label(__('admin.billing.seat_limit'))->numeric()->minValue(1),
            TextInput::make('sort')->label(__('admin.billing.sort'))->numeric()->default(0),
            Toggle::make('is_active')->label(__('admin.billing.is_active'))->default(true),
            Textarea::make('description')->label(__('admin.billing.description'))->rows(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('admin.billing.plan_name'))->searchable()->sortable(),
                TextColumn::make('code')->label(__('admin.billing.code'))->badge()->color('gray'),
                MoneyInput::column('price_rials')->label(__('admin.billing.price'))->sortable(),
                TextColumn::make('billing_period')->label(__('admin.billing.billing_period'))->formatStateUsing(fn (BillingPeriod $state): string => $state->label()),
                TextColumn::make('call_quota')->label(__('admin.billing.call_quota'))->numeric()->placeholder(__('admin.billing.unlimited')),
                TextColumn::make('seat_limit')->label(__('admin.billing.seat_limit'))->numeric()->placeholder(__('admin.billing.unlimited')),
                TextColumn::make('active_subscriptions_count')->label(__('admin.billing.active_subscriptions'))->counts(['subscriptions as active_subscriptions_count' => fn ($query) => $query->current()]),
                IconColumn::make('is_active')->label(__('admin.billing.is_active'))->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('sort');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePlans::route('/'),
        ];
    }
}
