<?php

namespace App\Filament\Resources\LlmModels\Schemas;

use App\Models\LlmProvider;
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
                Section::make(__('filament.sections.transcription'))
                    ->description(__('filament.sections.transcription_description'))
                    ->schema([
                        Toggle::make('is_transcription_default')
                            ->label(__('filament.fields.transcription_default'))
                            ->helperText(__('filament.misc.transcription_default_helper')),
                        Toggle::make('sends_audio_file')
                            ->label(__('filament.fields.sends_audio_file'))
                            ->helperText(__('filament.misc.sends_audio_file_helper'))
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }
}
