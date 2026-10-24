<?php

namespace App\Filament\Pages\Finance;

use App\Domain\Platform\Enums\AdminArea;
use App\Filament\Concerns\RequiresAdminArea;
use App\Filament\Support\MoneyInput;
use App\Models\AdminSetting;
use App\Services\Billing\BillingService;
use App\Support\Money;
use BackedEnum;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class FinanceSettings extends Page implements HasForms
{
    use InteractsWithForms;
    use RequiresAdminArea;

    protected static ?string $slug = 'finance-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?int $navigationSort = 9;

    protected string $view = 'filament.pages.finance.finance-settings';

    public ?array $data = [];

    public static function adminArea(): AdminArea
    {
        return AdminArea::Finance;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.finance_settings');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.groups.finance');
    }

    public function getTitle(): string
    {
        return __('admin.navigation.finance_settings');
    }

    public function mount(): void
    {
        $this->form->fill([
            'currency_display' => Money::unit(),
            'vat_rate' => (float) AdminSetting::valueFor(AdminSetting::VAT_RATE, BillingService::DEFAULT_VAT_RATE),
            'invoice_due_days' => (int) AdminSetting::valueFor(AdminSetting::INVOICE_DUE_DAYS, BillingService::DEFAULT_DUE_DAYS),
            'monthly_ai_budget_rials' => AdminSetting::valueFor(AdminSetting::MONTHLY_AI_BUDGET_RIALS),
            'seller' => (array) AdminSetting::valueFor(AdminSetting::SELLER_PROFILE, []),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.finance.settings.general'))
                    ->columns(2)
                    ->schema([
                        Radio::make('currency_display')->label(__('admin.finance.settings.currency_display'))
                            ->options([Money::TOMAN => 'تومان', Money::RIAL => 'ریال'])->inline()->required(),
                        TextInput::make('vat_rate')->label(__('admin.finance.settings.vat_rate'))->numeric()->minValue(0)->maxValue(100)->suffix('٪')->required(),
                        TextInput::make('invoice_due_days')->label(__('admin.finance.settings.invoice_due_days'))->numeric()->integer()->minValue(0)->maxValue(120)->required(),
                        MoneyInput::make('monthly_ai_budget_rials')->label(__('admin.finance.settings.monthly_ai_budget'))
                            ->helperText(__('admin.finance.settings.monthly_ai_budget_help')),
                    ]),
                Section::make(__('admin.finance.settings.seller_profile'))
                    ->description(__('admin.finance.settings.seller_profile_help'))
                    ->columns(2)
                    ->statePath('seller')
                    ->schema([
                        TextInput::make('name')->label(__('admin.billing.legal_name'))->maxLength(255),
                        TextInput::make('national_id')->label(__('admin.billing.national_id'))->maxLength(20),
                        TextInput::make('economic_code')->label(__('admin.billing.economic_code'))->maxLength(20),
                        TextInput::make('postal_code')->label(__('admin.billing.postal_code'))->maxLength(20),
                        TextInput::make('phone')->label(__('admin.billing.billing_phone'))->maxLength(30),
                        Textarea::make('address')->label(__('admin.billing.billing_address'))->rows(2)->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $budget = (int) ($data['monthly_ai_budget_rials'] ?? 0);

        AdminSetting::store(AdminSetting::VAT_RATE, (float) $data['vat_rate']);
        AdminSetting::store(AdminSetting::INVOICE_DUE_DAYS, (int) $data['invoice_due_days']);
        AdminSetting::store(AdminSetting::MONTHLY_AI_BUDGET_RIALS, $budget > 0 ? $budget : null);
        AdminSetting::store(AdminSetting::SELLER_PROFILE, $data['seller'] ?? []);
        AdminSetting::store(AdminSetting::CURRENCY_DISPLAY, $data['currency_display']);
        Money::forgetUnit();

        Notification::make()->title(__('admin.common.saved'))->success()->send();

        $this->mount();
    }
}
