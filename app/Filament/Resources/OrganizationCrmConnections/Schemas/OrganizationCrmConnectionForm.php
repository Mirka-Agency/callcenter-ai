<?php

namespace App\Filament\Resources\OrganizationCrmConnections\Schemas;

use App\Domain\Crm\Enums\CrmProviderCode;
use App\Models\CrmProvider;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class OrganizationCrmConnectionForm
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
                        Select::make('crm_provider_id')
                            ->relationship('provider', 'name', fn ($query) => $query->where('is_active', true))
                            ->label(__('filament.fields.crm_provider'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (mixed $state, Set $set, Get $get): void {
                                $code = CrmProvider::query()->whereKey($state)->value('code');
                                $url = (string) $get('credentials.api_url');

                                if ($code === CrmProviderCode::Dynamics->value && ($url === '' || str_contains($url, 'didar.me'))) {
                                    $set('credentials.api_url', 'https://yourorg.crm.dynamics.com');
                                }

                                if ($code === CrmProviderCode::Didar->value && ($url === '' || str_contains($url, 'dynamics.com'))) {
                                    $set('credentials.api_url', CrmProviderCode::Didar->defaultApiUrl());
                                }
                            }),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Toggle::make('is_default')
                            ->label(__('filament.fields.default_connection')),
                        Toggle::make('is_active')
                            ->label(__('filament.fields.crm_enabled_for_org'))
                            ->helperText(__('filament.misc.crm_enable_admin_only_helper'))
                            ->default(true),
                    ])
                    ->columns(2),
                Section::make(__('filament.sections.credentials'))
                    ->schema([
                        TextInput::make('credentials.api_url')
                            ->label(fn (Get $get): string => self::isDynamics($get)
                                ? __('filament.fields.dynamics_environment_url')
                                : __('filament.fields.api_url'))
                            ->url()
                            ->required()
                            ->helperText(fn (Get $get): ?string => self::isDynamics($get)
                                ? __('filament.misc.dynamics_environment_url_helper')
                                : null)
                            ->default('https://app.didar.me/api'),
                        TextInput::make('credentials.tenant_id')
                            ->label(__('filament.fields.dynamics_tenant_id'))
                            ->helperText(__('filament.misc.dynamics_tenant_id_helper'))
                            ->visible(fn (Get $get): bool => self::isDynamics($get))
                            ->required(fn (Get $get): bool => self::isDynamics($get)),
                        TextInput::make('credentials.api_key')
                            ->label(fn (Get $get): string => self::isDynamics($get)
                                ? __('filament.fields.dynamics_client_id')
                                : __('filament.fields.api_key'))
                            ->password()
                            ->revealable()
                            ->helperText(fn (Get $get): ?string => self::isDynamics($get)
                                ? __('filament.misc.dynamics_client_id_helper')
                                : null),
                        TextInput::make('credentials.api_token')
                            ->label(fn (Get $get): string => self::isDynamics($get)
                                ? __('filament.fields.dynamics_client_secret')
                                : __('filament.fields.api_token'))
                            ->password()
                            ->revealable()
                            ->helperText(fn (Get $get): ?string => self::isDynamics($get)
                                ? __('filament.misc.dynamics_client_secret_helper')
                                : null),
                        TextInput::make('credentials.username')
                            ->label(__('filament.fields.username'))
                            ->visible(fn (Get $get): bool => ! self::isDynamics($get)),
                        TextInput::make('credentials.password')
                            ->label(__('filament.fields.password'))
                            ->password()
                            ->revealable()
                            ->visible(fn (Get $get): bool => ! self::isDynamics($get)),
                    ])
                    ->columns(2),
                Section::make(__('filament.sections.settings'))
                    ->description(__('filament.misc.crm_settings_description'))
                    ->schema([
                        TextInput::make('settings.webhook_url')
                            ->label(__('filament.fields.webhook_url'))
                            ->url()
                            ->visible(fn (Get $get): bool => ! self::isDynamics($get)),
                        TextInput::make('settings.webhook_secret')
                            ->label(__('filament.fields.webhook_secret'))
                            ->password()
                            ->revealable()
                            ->visible(fn (Get $get): bool => ! self::isDynamics($get)),
                        TextInput::make('settings.timeout')
                            ->label(__('filament.fields.timeout_seconds'))
                            ->persianNumeric(0)
                            ->default(30)
                            ->minValue(5)
                            ->maxValue(120),
                    ])
                    ->columns(2),
                Section::make(__('filament.sections.crm_deal_defaults'))
                    ->description(__('filament.misc.crm_deal_defaults_description'))
                    ->schema([
                        TextInput::make('settings.pipeline_id')
                            ->label(__('filament.fields.pipeline_id'))
                            ->helperText(fn (Get $get): string => self::isDynamics($get)
                                ? __('filament.misc.dynamics_pipeline_id_helper')
                                : __('filament.misc.crm_pipeline_id_helper'))
                            ->maxLength(100),
                        TextInput::make('settings.pipeline_stage_id')
                            ->label(__('filament.fields.pipeline_stage_id'))
                            ->helperText(fn (Get $get): string => self::isDynamics($get)
                                ? __('filament.misc.dynamics_pipeline_stage_id_helper')
                                : __('filament.misc.crm_pipeline_stage_id_helper'))
                            ->maxLength(100),
                        TextInput::make('settings.deal_owner_id')
                            ->label(__('filament.fields.deal_owner_id'))
                            ->helperText(fn (Get $get): string => self::isDynamics($get)
                                ? __('filament.misc.dynamics_owner_id_helper')
                                : __('filament.misc.crm_deal_owner_id_helper'))
                            ->maxLength(100),
                    ])
                    ->columns(2),
            ]);
    }

    private static function isDynamics(Get $get): bool
    {
        $code = CrmProvider::query()->whereKey($get('crm_provider_id'))->value('code');

        return $code === CrmProviderCode::Dynamics->value;
    }
}
