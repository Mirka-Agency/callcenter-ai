<?php

namespace App\Filament\Resources\LandingCalculatorModels\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LandingCalculatorModelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.sections.landing_calculator'))
                    ->description(__('filament.sections.landing_calculator_description'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('filament.fields.name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('cost_per_minute_tomans')
                            ->label(__('filament.fields.cost_per_minute_tomans'))
                            ->persianNumeric(0)
                            ->required()
                            ->minValue(0)
                            ->helperText(__('filament.fields.cost_per_minute_tomans_helper')),
                        TextInput::make('position')
                            ->label(__('filament.fields.landing_calculator_position'))
                            ->persianNumeric(0)
                            ->minValue(0)
                            ->default(0)
                            ->helperText(__('filament.fields.landing_calculator_position_helper')),
                        Toggle::make('is_default')
                            ->label(__('filament.fields.default'))
                            ->helperText(__('filament.fields.landing_calculator_default_helper'))
                            ->default(false),
                        Toggle::make('is_active')
                            ->label(__('filament.fields.active'))
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }
}
