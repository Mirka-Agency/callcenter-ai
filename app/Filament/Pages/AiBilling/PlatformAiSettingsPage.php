<?php

namespace App\Filament\Pages\AiBilling;

use App\Filament\Concerns\OnlySuperAdmin;
use App\Models\PlatformAiSettings;
use App\Support\OnPrem;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PlatformAiSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;
    use OnlySuperAdmin;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 3;

    protected static string $routePath = 'ai-billing/platform-settings';

    protected string $view = 'filament.pages.ai-billing.platform-ai-settings';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.platform_billing');
    }

    protected static function isAvailableInCurrentDeployment(): bool
    {
        return ! OnPrem::billingHidden();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.groups.ai_billing');
    }

    public function getTitle(): string
    {
        return __('filament.pages.platform_billing_settings');
    }

    public function mount(): void
    {
        $settings = PlatformAiSettings::current();

        $this->form->fill([
            'allow_negative_balance' => $settings->allow_negative_balance,
            'currency' => $settings->currency,
            'billing_unit_currency' => $settings->billing_unit_currency ?? 'USD',
            'billing_unit_price' => $settings->billing_unit_price ?? 500_000,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.sections.billing'))
                    ->schema([
                        TextInput::make('currency')
                            ->label(__('filament.fields.wallet_currency'))
                            ->default('IRR')
                            ->maxLength(3)
                            ->required()
                            ->helperText(__('filament.fields.wallet_currency_helper')),
                        Toggle::make('allow_negative_balance')
                            ->label(__('filament.fields.allow_negative_balances'))
                            ->helperText(__('filament.fields.allow_negative_balances_helper')),
                    ])
                    ->columns(2),
                Section::make(__('filament.sections.billing_unit'))
                    ->description(__('filament.sections.billing_unit_description'))
                    ->schema([
                        TextInput::make('billing_unit_currency')
                            ->label(__('filament.fields.billing_unit_currency'))
                            ->default('USD')
                            ->maxLength(3)
                            ->required()
                            ->helperText(__('filament.fields.billing_unit_currency_helper')),
                        TextInput::make('billing_unit_price')
                            ->label(fn (callable $get) => __('filament.fields.billing_unit_price').' ('.($get('currency') ?: PlatformAiSettings::currencyCode()).')')
                            ->persianNumeric(null, 2)
                            ->required()
                            ->minValue(0)
                            ->step(0.01)
                            ->helperText(__('filament.fields.billing_unit_price_helper')),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        PlatformAiSettings::current()->update([
            'currency' => $data['currency'],
            'billing_unit_currency' => $data['billing_unit_currency'],
            'billing_unit_price' => $data['billing_unit_price'],
            'allow_negative_balance' => $data['allow_negative_balance'],
        ]);

        Notification::make()
            ->title(__('filament.notifications.platform_billing_saved'))
            ->success()
            ->send();
    }
}
