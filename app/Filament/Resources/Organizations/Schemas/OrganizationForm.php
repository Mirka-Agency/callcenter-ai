<?php

namespace App\Filament\Resources\Organizations\Schemas;

use App\Domain\Crm\OrganizationCrmMode;
use App\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class OrganizationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Textarea::make('business_context')
                    ->label(__('filament.fields.business_context'))
                    ->helperText(__('filament.misc.business_context_helper'))
                    ->rows(5)
                    ->maxLength(5000)
                    ->columnSpanFull(),
                Toggle::make('disabled')
                    ->label(__('filament.fields.disabled')),
                Toggle::make('employer_can_manage_integrations')
                    ->label(__('filament.fields.employer_can_manage_integrations'))
                    ->helperText(__('filament.misc.employer_can_manage_integrations_helper'))
                    ->default(false),
                Select::make('crm_mode')
                    ->label(__('filament.fields.crm_mode'))
                    ->options(OrganizationCrmMode::options())
                    ->default(OrganizationCrmMode::Internal->value)
                    ->required()
                    ->native(false)
                    ->helperText(__('filament.misc.crm_mode_helper')),
                Select::make('user_id')
                    ->label(__('filament.fields.employer'))
                    ->relationship(
                        name: 'employer',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('role', UserRole::Employer),
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->native(false),
                Section::make(__('admin.billing.profile_heading'))
                    ->description(__('admin.billing.profile_description'))
                    ->collapsible()
                    ->collapsed()
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('legal_name')->label(__('admin.billing.legal_name'))->maxLength(255),
                        TextInput::make('national_id')->label(__('admin.billing.national_id'))->maxLength(20),
                        TextInput::make('economic_code')->label(__('admin.billing.economic_code'))->maxLength(20),
                        TextInput::make('postal_code')->label(__('admin.billing.postal_code'))->maxLength(20),
                        TextInput::make('billing_phone')->label(__('admin.billing.billing_phone'))->tel()->maxLength(30),
                        Textarea::make('billing_address')->label(__('admin.billing.billing_address'))->rows(2)->columnSpanFull(),
                    ]),
            ]);
    }
}
