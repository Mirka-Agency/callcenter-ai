<?php

namespace App\Filament\Resources\VoipProviders\Schemas;

use App\Domain\Voip\Enums\VoipProviderCode;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class VoipProviderForm
{
    public static function configure(Schema $schema): Schema
    {
        /** @var array<string, class-string> $adapterMap */
        $adapterMap = config('voip.adapters', []);

        /** @var array<string, string> $adapterOptions */
        $adapterOptions = collect($adapterMap)
            ->mapWithKeys(function (mixed $class, string $code): array {
                if (! is_string($class) || $class === '') {
                    return [];
                }

                $label = $code;

                try {
                    $label = VoipProviderCode::from($code)->label();
                } catch (\Throwable) {
                    // Keep $label as-is for unknown codes.
                }

                return [$class => $label];
            })
            ->all();

        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('code')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->options(collect(VoipProviderCode::cases())->mapWithKeys(
                        fn (VoipProviderCode $code): array => [$code->value => "{$code->label()} ({$code->value})"],
                    )->all())
                    ->native(false)
                    ->searchable()
                    ->helperText(__('filament.misc.voip_provider_code_helper')),
                Select::make('adapter_class')
                    ->label(__('filament.fields.provider_adapter'))
                    ->options($adapterOptions)
                    ->searchable()
                    ->preload()
                    ->required()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set, Get $get) use ($adapterMap): void {
                        if (! $state) {
                            return;
                        }

                        $config = $get('config');
                        $config = is_array($config) ? $config : [];

                        $code = array_search($state, $adapterMap, true);

                        if (is_string($code) && $code !== '' && blank($get('code'))) {
                            $set('code', $code);
                        }

                        // Only seed defaults when missing, to avoid overwriting user edits.
                        $defaults = match ($code) {
                            VoipProviderCode::Novatel->value => ['default_api_url' => 'https://api.navatel.ir/v1'],
                            VoipProviderCode::Simotel->value => ['default_api_url' => 'http://your-simotel-host/API/v4'],
                            VoipProviderCode::Custom->value => ['default_api_url' => null],
                            default => [],
                        };

                        foreach ($defaults as $key => $value) {
                            if (! array_key_exists($key, $config)) {
                                $config[$key] = $value;
                            }
                        }

                        $set('config', $config);
                    }),
                Toggle::make('is_active')
                    ->label(__('filament.fields.active'))
                    ->default(true),
                KeyValue::make('config')
                    ->label(__('filament.descriptions.provider_configuration'))
                    ->keyLabel(__('filament.fields.key'))
                    ->valueLabel(__('filament.fields.value'))
                    ->reorderable(),
            ]);
    }
}
