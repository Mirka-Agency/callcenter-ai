<?php

namespace App\Filament\Resources\Organizations\Tables;

use App\Domain\Crm\OrganizationCrmMode;
use App\Filament\Resources\Organizations\OrganizationResource;
use App\Filament\Support\CsvExport;
use App\Filament\Support\DemoOrganizationCleanupActions;
use App\Models\Organization;
use App\Services\PlatformAnalytics\OrganizationHealthScorer;
use App\Support\JalaliDate;
use App\Support\PersianNumber;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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
                TextColumn::make('health')
                    ->label(__('filament.platform_analytics.health_score'))
                    ->badge()
                    ->state(fn (Organization $record): ?int => app(OrganizationHealthScorer::class)->for($record)?->score)
                    ->formatStateUsing(function (int $state, Organization $record): string {
                        $health = app(OrganizationHealthScorer::class)->for($record);

                        return $health->label().' · '.PersianNumber::format($state, 0);
                    })
                    ->color(fn (Organization $record): string => app(OrganizationHealthScorer::class)->for($record)?->color() ?? 'gray')
                    ->tooltip(fn (Organization $record): ?string => implode('، ', app(OrganizationHealthScorer::class)->for($record)?->reasonLabels() ?? []) ?: null)
                    ->placeholder('—'),
                TextColumn::make('employees_count')
                    ->counts('employees')
                    ->label(__('filament.fields.employees'))
                    ->sortable(),
                TextColumn::make('crm_mode')
                    ->label(__('filament.fields.crm_mode'))
                    ->badge()
                    ->formatStateUsing(fn (OrganizationCrmMode|string|null $state): string => match (true) {
                        $state instanceof OrganizationCrmMode => $state->label(),
                        is_string($state) => OrganizationCrmMode::tryFrom($state)?->label() ?? $state,
                        default => OrganizationCrmMode::Internal->label(),
                    })
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
                SelectFilter::make('crm_mode')
                    ->label(__('filament.fields.crm_mode'))
                    ->options(OrganizationCrmMode::options()),
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
            ->recordUrl(fn (Organization $record): string => OrganizationResource::getUrl('overview', ['record' => $record]))
            ->recordActions([
                Action::make('overview')
                    ->label(__('admin.organization_overview.tab'))
                    ->icon(Heroicon::OutlinedPresentationChartBar)
                    ->color('gray')
                    ->url(fn (Organization $record): string => OrganizationResource::getUrl('overview', ['record' => $record])),
                EditAction::make(),
                DemoOrganizationCleanupActions::deleteRecord(),
            ])
            ->headerActions([
                CsvExport::action('organizations', fn () => OrganizationsTable::exportRows()),
            ])
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function (DeleteBulkAction $action, Collection $records): void {
                            if ($records->contains(fn (Organization $record): bool => $record->is_demo)) {
                                Notification::make()
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

    /** @return iterable<array<string, mixed>> */
    public static function exportRows(): iterable
    {
        $scorer = app(OrganizationHealthScorer::class);

        foreach (Organization::query()->with(['employer', 'currentSubscription.plan'])->withCount('employees')->lazyById() as $organization) {
            $health = $scorer->for($organization);

            yield [
                __('filament.fields.title') => $organization->title,
                __('filament.fields.employer') => $organization->employer?->name,
                __('filament.fields.employer_email') => $organization->employer?->email,
                __('filament.fields.employees') => $organization->employees_count,
                __('filament.platform_analytics.health_score') => $health?->score,
                __('admin.billing.plan') => $organization->currentSubscription?->plan?->name,
                __('admin.billing.subscription_status') => $organization->currentSubscription?->status->label(),
                __('filament.fields.demo') => $organization->is_demo ? __('admin.common.yes') : __('admin.common.no'),
                __('filament.fields.disabled') => $organization->disabled ? __('admin.common.yes') : __('admin.common.no'),
                __('filament.fields.created_at') => JalaliDate::datetime($organization->created_at),
            ];
        }
    }
}
