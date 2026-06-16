<?php

namespace App\Filament\Pages\AiBilling;

use App\Domain\Billing\Enums\ConversationEstimateType;
use App\Filament\Concerns\OnlySuperAdmin;
use App\Models\PlatformAiSettings;
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
use UnitEnum;

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
        $ratios = $settings->estimation_conversation_ratios ?? PlatformAiSettings::defaultConversationRatios();

        $this->form->fill([
            'allow_negative_balance' => $settings->allow_negative_balance,
            'currency' => $settings->currency,
            'billing_unit_currency' => $settings->billing_unit_currency ?? 'USD',
            'billing_unit_price' => $settings->billing_unit_price ?? 500_000,
            'estimation_words_per_minute' => $settings->estimation_words_per_minute,
            'estimation_tokens_per_word' => $settings->estimation_tokens_per_word,
            'ratio_short_support' => $ratios[ConversationEstimateType::ShortSupport->value] ?? 0.15,
            'ratio_sales' => $ratios[ConversationEstimateType::Sales->value] ?? 0.25,
            'ratio_consultation' => $ratios[ConversationEstimateType::Consultation->value] ?? 0.35,
            'ratio_long_meeting' => $ratios[ConversationEstimateType::LongMeeting->value] ?? 0.50,
            'ratio_custom' => $ratios[ConversationEstimateType::Custom->value] ?? 0.30,
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
                Section::make(__('filament.sections.cost_estimation_defaults'))
                    ->description(__('filament.sections.cost_estimation_defaults_description'))
                    ->schema([
                        TextInput::make('estimation_words_per_minute')
                            ->label(__('filament.fields.average_words_per_minute'))
                            ->persianNumeric(0)
                            ->required()
                            ->minValue(60)
                            ->maxValue(300)
                            ->default(150)
                            ->helperText(__('filament.fields.average_words_per_minute_helper')),
                        TextInput::make('estimation_tokens_per_word')
                            ->label(__('filament.fields.tokens_per_word'))
                            ->persianNumeric(null, 2)
                            ->required()
                            ->minValue(0.5)
                            ->maxValue(3)
                            ->step(0.01)
                            ->default(1.30),
                        TextInput::make('ratio_short_support')
                            ->label(__('filament.fields.ratio_short_support'))
                            ->persianNumeric(null, 2)
                            ->required()
                            ->minValue(0)
                            ->maxValue(2)
                            ->step(0.01),
                        TextInput::make('ratio_sales')
                            ->label(__('filament.fields.ratio_sales'))
                            ->persianNumeric(null, 2)
                            ->required()
                            ->minValue(0)
                            ->maxValue(2)
                            ->step(0.01),
                        TextInput::make('ratio_consultation')
                            ->label(__('filament.fields.ratio_consultation'))
                            ->persianNumeric(null, 2)
                            ->required()
                            ->minValue(0)
                            ->maxValue(2)
                            ->step(0.01),
                        TextInput::make('ratio_long_meeting')
                            ->label(__('filament.fields.ratio_long_meeting'))
                            ->persianNumeric(null, 2)
                            ->required()
                            ->minValue(0)
                            ->maxValue(2)
                            ->step(0.01),
                        TextInput::make('ratio_custom')
                            ->label(__('filament.fields.ratio_custom'))
                            ->persianNumeric(null, 2)
                            ->required()
                            ->minValue(0)
                            ->maxValue(2)
                            ->step(0.01)
                            ->helperText(__('filament.fields.ratio_custom_helper')),
                    ])
                    ->columns(2)
                    ->collapsed(),
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
            'estimation_words_per_minute' => (int) $data['estimation_words_per_minute'],
            'estimation_tokens_per_word' => $data['estimation_tokens_per_word'],
            'estimation_conversation_ratios' => [
                ConversationEstimateType::ShortSupport->value => (float) $data['ratio_short_support'],
                ConversationEstimateType::Sales->value => (float) $data['ratio_sales'],
                ConversationEstimateType::Consultation->value => (float) $data['ratio_consultation'],
                ConversationEstimateType::LongMeeting->value => (float) $data['ratio_long_meeting'],
                ConversationEstimateType::Custom->value => (float) $data['ratio_custom'],
            ],
        ]);

        Notification::make()
            ->title(__('filament.notifications.platform_billing_saved'))
            ->success()
            ->send();
    }
}
