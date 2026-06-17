<?php

namespace App\Filament\Resources\LlmProviders\Schemas;

use App\Domain\Llm\Enums\LlmProviderCode;
use App\Models\LlmProvider;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LlmProviderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.sections.provider'))
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('code')
                            ->label(__('filament.fields.provider_adapter'))
                            ->options(collect(LlmProviderCode::cases())->mapWithKeys(
                                fn (LlmProviderCode $code) => [$code->value => $code->label()],
                            ))
                            ->required()
                            ->helperText(__('filament.misc.provider_code_helper'))
                            ->disabledOn('edit'),
                        Toggle::make('is_active')
                            ->label(__('filament.fields.active'))
                            ->default(true),
                    ])
                    ->columns(2),
                Section::make(__('filament.sections.api_credentials'))
                    ->description(__('filament.misc.platform_api_key_description'))
                    ->schema([
                        TextInput::make('api_key')
                            ->label(__('filament.fields.api_key'))
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText(__('filament.misc.provider_api_key_helper')),
                        TextInput::make('base_url')
                            ->label(__('filament.fields.api_base_url'))
                            ->url()
                            ->helperText(__('filament.misc.api_base_url_helper')),
                    ])
                    ->columns(2),
                Section::make(__('filament.sections.extra_configuration'))
                    ->schema([
                        KeyValue::make('config')
                            ->label(__('filament.fields.additional_settings'))
                            ->keyLabel(__('filament.fields.key'))
                            ->valueLabel(__('filament.fields.value'))
                            ->reorderable(),
                    ])
                    ->collapsed(),
            ]);
    }
}
