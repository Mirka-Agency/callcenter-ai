<?php

namespace App\Filament\Resources\Coupons;

use App\Domain\Platform\Enums\AdminArea;
use App\Domain\Platform\Enums\CouponType;
use App\Filament\Concerns\RequiresAdminArea;
use App\Filament\Resources\Coupons\Pages\ManageCoupons;
use App\Models\Coupon;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CouponResource extends Resource
{
    use RequiresAdminArea;

    protected static ?string $model = Coupon::class;

    protected static ?string $slug = 'coupons';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?int $navigationSort = 5;

    public static function adminArea(): AdminArea
    {
        return AdminArea::Finance;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.coupons');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('filament.navigation.groups.finance');
    }

    public static function getModelLabel(): string
    {
        return __('admin.models.coupon');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.navigation.coupons');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('code')->label(__('admin.billing.code'))->required()->alphaDash()->maxLength(50)->unique(ignoreRecord: true),
            Select::make('discount_type')->label(__('admin.billing.discount_type'))->options(CouponType::options())->required()->native(false)->live(),
            TextInput::make('discount_value')->label(__('admin.billing.discount_value'))->numeric()->minValue(1)->required()
                ->helperText(fn ($get): string => $get('discount_type') === CouponType::Percent->value ? __('admin.billing.percent_help') : __('admin.billing.fixed_help', ['unit' => 'ریال'])),
            DateTimePicker::make('expires_at')->label(__('admin.billing.expires_at'))->jalali(),
            TextInput::make('max_redemptions')->label(__('admin.billing.max_redemptions'))->numeric()->minValue(1),
            Toggle::make('is_active')->label(__('admin.billing.is_active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label(__('admin.billing.code'))->searchable()->badge(),
                TextColumn::make('discount')->label(__('admin.billing.discount'))
                    ->state(fn (Coupon $record): string => $record->discount_type === CouponType::Percent ? $record->discount_value.'٪' : Money::format($record->discount_value)),
                TextColumn::make('redemptions')->label(__('admin.billing.redemptions'))
                    ->formatStateUsing(fn (int $state, Coupon $record): string => $state.($record->max_redemptions ? ' / '.$record->max_redemptions : '')),
                TextColumn::make('expires_at')->label(__('admin.billing.expires_at'))->jalaliDateTime()->placeholder('—'),
                IconColumn::make('is_active')->label(__('admin.billing.is_active'))->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCoupons::route('/'),
        ];
    }
}
