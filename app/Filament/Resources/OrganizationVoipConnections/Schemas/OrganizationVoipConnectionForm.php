<?php

namespace App\Filament\Resources\OrganizationVoipConnections\Schemas;

use App\Domain\Voip\Enums\VoipProviderCode;
use App\Infrastructure\Voip\Adapters\CustomVoipAdapter;
use App\Models\OrganizationVoipConnection;
use App\Models\VoipProvider;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class OrganizationVoipConnectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.sections.connection'))
                    ->schema([
                        Select::make('organization_id')
                            ->relationship('organization', 'title')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                        Select::make('voip_provider_id')
                            ->relationship('provider', 'name', fn ($query) => $query->where('is_active', true))
                            ->label(__('filament.fields.voip_provider'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                if (! $state) {
                                    return;
                                }

                                $provider = VoipProvider::query()->find($state);
                                $defaultUrl = $provider?->config['default_api_url'] ?? null;

                                if (is_string($defaultUrl) && $defaultUrl !== '') {
                                    $set('credentials.api_url', $defaultUrl);
                                }
                            }),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Toggle::make('is_default')
                            ->label(__('filament.fields.default_connection')),
                        Toggle::make('is_active')
                            ->label(__('filament.fields.active'))
                            ->default(true),
                    ])
                    ->columns(2),
                Section::make(__('filament.sections.credentials'))
                    ->schema([
                        TextInput::make('credentials.api_url')
                            ->label(__('filament.fields.api_url'))
                            ->url()
                            ->required(fn (Get $get, ?OrganizationVoipConnection $record): bool => ! self::isCustomProvider($get('voip_provider_id'), $record))
                            ->helperText(fn (Get $get, ?OrganizationVoipConnection $record): string => self::isCustomProvider($get('voip_provider_id'), $record)
                                ? __('filament.misc.voip_custom_api_url_helper')
                                : __('filament.misc.voip_api_url_helper')),
                        TextInput::make('credentials.api_key')
                            ->label(__('filament.fields.api_key'))
                            ->password()
                            ->revealable()
                            ->helperText(__('filament.misc.voip_simotel_api_key_helper'))
                            ->visible(fn (Get $get, ?OrganizationVoipConnection $record): bool => ! self::isCustomProvider($get('voip_provider_id'), $record)),
                        TextInput::make('credentials.api_token')
                            ->label(__('filament.fields.api_token'))
                            ->password()
                            ->revealable()
                            ->helperText(__('filament.misc.voip_simotel_api_token_helper'))
                            ->visible(fn (Get $get, ?OrganizationVoipConnection $record): bool => ! self::isCustomProvider($get('voip_provider_id'), $record)),
                        TextInput::make('credentials.username')
                            ->label(__('filament.fields.username'))
                            ->helperText(__('filament.misc.voip_simotel_basic_auth_helper'))
                            ->visible(fn (Get $get, ?OrganizationVoipConnection $record): bool => ! self::isCustomProvider($get('voip_provider_id'), $record)),
                        TextInput::make('credentials.password')
                            ->label(__('filament.fields.password'))
                            ->password()
                            ->revealable()
                            ->visible(fn (Get $get, ?OrganizationVoipConnection $record): bool => ! self::isCustomProvider($get('voip_provider_id'), $record)),
                    ])
                    ->columns(2)
                    ->visible(fn (Get $get, ?OrganizationVoipConnection $record): bool => ! self::isCustomProvider($get('voip_provider_id'), $record)),
                Section::make(__('filament.sections.settings'))
                    ->schema([
                        TextInput::make('webhook_token')
                            ->label(__('filament.fields.voip_webhook_token'))
                            ->maxLength(64)
                            ->regex('/^[A-Za-z0-9]{48}$/')
                            ->unique(ignoreRecord: true)
                            ->required(fn (?OrganizationVoipConnection $record): bool => $record !== null)
                            ->live(onBlur: true)
                            ->dehydrateStateUsing(function (?string $state, ?OrganizationVoipConnection $record): ?string {
                                $normalized = OrganizationVoipConnection::normalizeWebhookTokenInput($state);

                                if ($normalized === null && $record !== null) {
                                    return $record->webhook_token;
                                }

                                return $normalized;
                            })
                            ->helperText(__('filament.misc.voip_webhook_token_helper'))
                            ->validationMessages([
                                'unique' => __('filament.validation.voip_webhook_token_unique'),
                                'regex' => __('filament.validation.voip_webhook_token_format'),
                            ]),
                        Placeholder::make('inbound_webhook_url')
                            ->label(__('filament.fields.voip_inbound_webhook_url'))
                            ->content(function (Get $get, ?OrganizationVoipConnection $record): string {
                                $token = OrganizationVoipConnection::normalizeWebhookTokenInput($get('webhook_token'))
                                    ?? $record?->webhook_token;

                                if (blank($token)) {
                                    return __('filament.misc.voip_inbound_webhook_pending');
                                }

                                return route('webhooks.voip', ['token' => $token]);
                            })
                            ->copyable(fn (Get $get, ?OrganizationVoipConnection $record): bool => filled(
                                OrganizationVoipConnection::normalizeWebhookTokenInput($get('webhook_token'))
                                    ?? $record?->webhook_token
                            ))
                            ->helperText(__('filament.misc.voip_inbound_webhook_helper')),
                        Placeholder::make('custom_webhook_payload')
                            ->label(__('filament.fields.voip_custom_webhook_payload'))
                            ->content(fn (): string => json_encode(CustomVoipAdapter::sampleWebhookPayload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                            ->copyable()
                            ->helperText(__('filament.misc.voip_custom_webhook_payload_helper'))
                            ->visible(fn (Get $get, ?OrganizationVoipConnection $record): bool => self::isCustomProvider($get('voip_provider_id'), $record)),
                        KeyValue::make('settings.webhook_field_mapping')
                            ->label(__('filament.fields.webhook_field_mapping'))
                            ->keyLabel(__('filament.fields.internal_field'))
                            ->valueLabel(__('filament.fields.payload_path'))
                            ->helperText(__('filament.misc.voip_webhook_field_mapping_helper'))
                            ->visible(fn (Get $get, ?OrganizationVoipConnection $record): bool => self::isCustomProvider($get('voip_provider_id'), $record)),
                        TextInput::make('settings.extra.context')
                            ->label(__('filament.fields.simotel_context'))
                            ->maxLength(255)
                            ->helperText(__('filament.misc.voip_simotel_context_helper'))
                            ->visible(fn (Get $get, ?OrganizationVoipConnection $record): bool => ! self::isCustomProvider($get('voip_provider_id'), $record)),
                        KeyValue::make('settings.extension_mapping')
                            ->label(__('filament.fields.extension_mapping'))
                            ->keyLabel(__('filament.fields.extension'))
                            ->valueLabel(__('filament.fields.mapped_value'))
                            ->helperText(__('filament.misc.voip_extension_mapping_helper')),
                        KeyValue::make('settings.recording_settings')
                            ->label(__('filament.fields.recording_settings'))
                            ->keyLabel(__('filament.fields.key'))
                            ->valueLabel(__('filament.fields.value')),
                        TextInput::make('settings.timeout')
                            ->label(__('filament.fields.timeout_seconds'))
                            ->persianNumeric(0)
                            ->default(30)
                            ->minValue(5)
                            ->maxValue(120)
                            ->visible(fn (Get $get, ?OrganizationVoipConnection $record): bool => ! self::isCustomProvider($get('voip_provider_id'), $record)),
                    ])
                    ->columns(1),
            ]);
    }

    private static function isCustomProvider(mixed $providerId, ?OrganizationVoipConnection $record): bool
    {
        if ($record?->relationLoaded('provider') && $record->provider) {
            return $record->provider->code === VoipProviderCode::Custom->value;
        }

        if ($record && ! $record->relationLoaded('provider')) {
            $record->loadMissing('provider');

            if ($record->provider?->code === VoipProviderCode::Custom->value) {
                return true;
            }
        }

        if (! $providerId) {
            return false;
        }

        return VoipProvider::query()
            ->whereKey($providerId)
            ->value('code') === VoipProviderCode::Custom->value;
    }
}
