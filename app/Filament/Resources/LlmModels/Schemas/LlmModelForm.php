<?php

namespace App\Filament\Resources\LlmModels\Schemas;

use App\Models\LlmProvider;
use App\Models\PlatformAiSettings;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LlmModelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.sections.model_details'))
                    ->schema([
                        Select::make('provider_id')
                            ->label(__('filament.fields.provider'))
                            ->options(fn () => LlmProvider::query()->orderBy('name')->pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('model_key')
                            ->label(__('filament.fields.model_key'))
                            ->required()
                            ->maxLength(255)
                            ->helperText(__('filament.misc.model_key_helper')),
                    ])
                    ->columns(2),
                Section::make(__('filament.sections.token_pricing'))
                    ->description(__('filament.sections.token_pricing_description'))
                    ->schema([
                        TextInput::make('input_price_per_million_tokens')
                            ->label(fn () => __('filament.fields.input_price_per_1m').' ('.PlatformAiSettings::billingUnitCurrency().')')
                            ->persianNumeric(null, 6)
                            ->required()
                            ->minValue(0)
                            ->step(0.000001),
                        TextInput::make('output_price_per_million_tokens')
                            ->label(fn () => __('filament.fields.output_price_per_1m').' ('.PlatformAiSettings::billingUnitCurrency().')')
                            ->persianNumeric(null, 6)
                            ->required()
                            ->minValue(0)
                            ->step(0.000001),
                        TextInput::make('cached_input_price_per_million_tokens')
                            ->label(fn () => __('filament.fields.cached_input_price_per_1m').' ('.PlatformAiSettings::billingUnitCurrency().')')
                            ->persianNumeric(null, 6)
                            ->minValue(0)
                            ->step(0.000001),
                        TextInput::make('reasoning_price_per_million_tokens')
                            ->label(fn () => __('filament.fields.reasoning_price_per_1m').' ('.PlatformAiSettings::billingUnitCurrency().')')
                            ->persianNumeric(null, 6)
                            ->minValue(0)
                            ->step(0.000001),
                    ])
                    ->columns(2),
                Section::make(__('filament.sections.settings'))
                    ->schema([
                        Toggle::make('is_default')
                            ->label(__('filament.fields.platform_default'))
                            ->helperText(__('filament.misc.platform_default_helper')),
                        Toggle::make('is_active')
                            ->label(__('filament.fields.active'))
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }
}
