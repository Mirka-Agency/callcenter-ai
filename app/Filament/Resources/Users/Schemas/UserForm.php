<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Domain\Platform\Enums\AdminArea;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\JalaliDate;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('filament.fields.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('filament.fields.email_address'))
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('phone')
                    ->label(__('filament.fields.phone'))
                    ->maxLength(20)
                    ->unique(ignoreRecord: true)
                    ->nullable(),
                Select::make('role')
                    ->options(UserRole::options())
                    ->required()
                    ->live()
                    ->native(false),
                CheckboxList::make('admin_areas')
                    ->label(__('admin.users.admin_areas'))
                    ->helperText(__('admin.users.admin_areas_help'))
                    ->options(AdminArea::options())
                    ->columns(3)
                    ->visible(fn (Get $get): bool => ($get('role') instanceof UserRole ? $get('role')->value : $get('role')) === UserRole::Admin->value)
                    ->columnSpanFull(),
                Placeholder::make('last_login_at')
                    ->label(__('filament.fields.last_login_at'))
                    ->content(fn (?User $record): string => $record?->last_login_at
                        ? JalaliDate::format($record->last_login_at, JalaliDate::DATETIME)
                        : __('filament.fields.never_logged_in'))
                    ->visible(fn (?User $record): bool => $record !== null),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('password')
                    ->label(__('filament.fields.password'))
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->maxLength(255),
            ]);
    }
}
