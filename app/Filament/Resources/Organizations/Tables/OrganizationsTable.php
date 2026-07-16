<?php

namespace App\Filament\Resources\Organizations\Tables;

use App\Filament\Support\DemoOrganizationCleanupActions;
use App\Models\Organization;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrganizationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_demo')
                    ->label(__('filament.fields.demo'))
                    ->boolean()
                    ->sortable(),
                TextColumn::make('employer.name')
                    ->label(__('filament.fields.employer'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employer.email')
                    ->label(__('filament.fields.employer_email'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('employer.last_login_at')
                    ->label(__('filament.fields.employer_last_login_at'))
                    ->jalaliDateTime()
                    ->sortable()
                    ->placeholder(__('filament.fields.never_logged_in')),
                TextColumn::make('employees_count')
                    ->counts('employees')
                    ->label(__('filament.fields.employees'))
                    ->sortable(),
                IconColumn::make('disabled')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->jalaliDateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->jalaliDateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_demo')
                    ->label(__('filament.fields.demo'))
                    ->nullable()
                    ->trueLabel(__('filament.fields.demo_only'))
                    ->falseLabel(__('filament.fields.production_only'))
                    ->queries(
                        true: fn (Builder $query) => $query->where('is_demo', true),
                        false: fn (Builder $query) => $query->where('is_demo', false),
                        blank: fn (Builder $query) => $query,
                    ),
                TernaryFilter::make('disabled')
                    ->label(__('filament.fields.status'))
                    ->nullable()
                    ->trueLabel(__('filament.fields.disabled'))
                    ->falseLabel(__('filament.fields.active'))
                    ->queries(
                        true: fn (Builder $query) => $query->where('disabled', true),
                        false: fn (Builder $query) => $query->where('disabled', false),
                        blank: fn (Builder $query) => $query,
                    ),
                SelectFilter::make('user_id')
                    ->label(__('filament.fields.employer'))
                    ->relationship('employer', 'name')
                    ->searchable()
                    ->preload(),
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
            ])
            ->recordActions([
                EditAction::make(),
                DemoOrganizationCleanupActions::deleteRecord(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function (DeleteBulkAction $action, \Illuminate\Support\Collection $records): void {
                            if ($records->contains(fn (Organization $record): bool => $record->is_demo)) {
                                \Filament\Notifications\Notification::make()
                                    ->title(__('filament.demo_cleanup.bulk_delete_blocked'))
                                    ->body(__('filament.demo_cleanup.use_demo_cleanup_action'))
                                    ->danger()
                                    ->send();

                                $action->halt();
                            }
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
