<?php

namespace App\Filament\Resources\Users\Tables;

use App\Application\Impersonation\Actions\StartImpersonationAction;
use App\Enums\UserRole;
use App\Filament\Resources\Organizations\OrganizationResource;
use App\Models\Organization;
use App\Models\User;
use App\Services\ImpersonationService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('filament.fields.email_address'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn (UserRole $state): string => $state->label())
                    ->color(fn (UserRole $state): string => match ($state) {
                        UserRole::SuperAdmin => 'danger',
                        UserRole::Admin => 'warning',
                        UserRole::Employer => 'info',
                        UserRole::Employee => 'success',
                    })
                    ->sortable(),
                TextColumn::make('organizations_list')
                    ->label(__('filament.fields.organization'))
                    ->getStateUsing(fn ($record) => $record->relatedOrganizations())
                    ->formatStateUsing(fn (Organization $state): string => $state->title)
                    ->listWithLineBreaks()
                    ->placeholder(__('filament.misc.em_dash'))
                    ->url(fn (Organization $state): string => OrganizationResource::getUrl('edit', ['record' => $state])),
                TextColumn::make('last_login_at')
                    ->label(__('filament.fields.last_login_at'))
                    ->jalaliDateTime()
                    ->sortable()
                    ->placeholder(__('filament.fields.never_logged_in')),
                TextColumn::make('email_verified_at')
                    ->label(__('filament.fields.verified'))
                    ->jalaliDateTime()
                    ->sortable()
                    ->placeholder(__('filament.fields.not_verified')),
                TextColumn::make('created_at')
                    ->jalaliDateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->jalaliDateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->multiple()
                    ->options(UserRole::options()),
                TernaryFilter::make('email_verified_at')
                    ->label(__('filament.fields.email_verified'))
                    ->nullable()
                    ->trueLabel(__('filament.fields.verified'))
                    ->falseLabel(__('filament.fields.not_verified'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('email_verified_at'),
                        false: fn (Builder $query) => $query->whereNull('email_verified_at'),
                        blank: fn (Builder $query) => $query,
                    ),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('created_from')
                            ->label(__('filament.fields.created_from')),
                        DatePicker::make('created_until')
                            ->label(__('filament.fields.created_until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
                Filter::make('updated_at')
                    ->schema([
                        DatePicker::make('updated_from')
                            ->label(__('filament.fields.updated_from')),
                        DatePicker::make('updated_until')
                            ->label(__('filament.fields.updated_until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['updated_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('updated_at', '>=', $date),
                            )
                            ->when(
                                $data['updated_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('updated_at', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                Action::make('loginAs')
                    ->label(__('filament.actions.login_as_user'))
                    ->icon(Heroicon::OutlinedArrowRightEndOnRectangle)
                    ->color('warning')
                    ->visible(fn (User $record): bool => auth()->user()?->role === UserRole::SuperAdmin
                        && in_array($record->role, [UserRole::Employer, UserRole::Employee], true))
                    ->tooltip(fn (User $record): ?string => auth()->user()?->role === UserRole::SuperAdmin
                        ? app(ImpersonationService::class)->impersonationDeniedReason(auth()->user(), $record)
                        : null)
                    ->requiresConfirmation()
                    ->modalHeading(__('filament.impersonation.modal_heading'))
                    ->modalDescription(fn (User $record): string => __('filament.impersonation.modal_description', [
                        'name' => $record->name,
                        'role' => $record->role->label(),
                    ]))
                    ->modalSubmitActionLabel(__('filament.actions.login_as_user'))
                    ->disabled(fn (User $record): bool => ! app(ImpersonationService::class)->canImpersonate(auth()->user(), $record))
                    ->action(function (User $record) {
                        $url = app(StartImpersonationAction::class)->execute(
                            admin: auth()->user(),
                            target: $record,
                            request: request(),
                        );

                        return redirect($url);
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
