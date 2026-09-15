<?php

namespace App\Filament\Resources\OrganizationVoipConnections\Tables;

use App\Application\Voip\Support\VoipReportFilter;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VoipReportTableFilters
{
    public static function configure(Table $table, array $filters): Table
    {
        return $table
            ->filters($filters)
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->deferFilters(false);
    }

    /** @return array<int, Filter|SelectFilter> */
    public static function forCallLogs(): array
    {
        return [
            self::callIdFilter(
                fn (Builder $query, ?string $value): Builder => VoipReportFilter::constrainCallLogsByCallId($query, $value),
            ),
            self::extensionFilter(
                fn (Builder $query, ?string $value): Builder => VoipReportFilter::constrainCallLogsByExtension($query, $value),
            ),
            self::directionFilter(),
        ];
    }

    /** @return array<int, Filter|SelectFilter> */
    public static function forWebhookLogs(): array
    {
        return [
            self::callIdFilter(
                fn (Builder $query, ?string $value): Builder => VoipReportFilter::constrainWebhookLogsByCallId($query, $value),
            ),
            self::extensionFilter(
                fn (Builder $query, ?string $value): Builder => VoipReportFilter::constrainWebhookLogsByExtension($query, $value),
            ),
            self::directionFilter(
                fn (Builder $query, ?string $value): Builder => VoipReportFilter::constrainWebhookLogsByDirection($query, $value),
            ),
        ];
    }

    /** @param  callable(Builder, ?string): Builder  $constrain */
    private static function callIdFilter(callable $constrain): Filter
    {
        return Filter::make('call_id')
            ->label(__('filament.fields.call_id'))
            ->schema([
                TextInput::make('value')
                    ->label(__('filament.fields.call_id'))
                    ->placeholder(__('filament.fields.call_id')),
            ])
            ->query(fn (Builder $query, array $data): Builder => $constrain($query, self::filterValue($data)))
            ->indicateUsing(fn (array $state): array => self::textIndicator(
                __('filament.fields.call_id'),
                $state['value'] ?? null,
            ));
    }

    /** @param  callable(Builder, ?string): Builder  $constrain */
    private static function extensionFilter(callable $constrain): Filter
    {
        return Filter::make('extension')
            ->label(__('filament.fields.internal_number'))
            ->schema([
                TextInput::make('value')
                    ->label(__('filament.fields.internal_number'))
                    ->placeholder(__('filament.fields.internal_number')),
            ])
            ->query(fn (Builder $query, array $data): Builder => $constrain($query, self::filterValue($data)))
            ->indicateUsing(fn (array $state): array => self::textIndicator(
                __('filament.fields.internal_number'),
                $state['value'] ?? null,
            ));
    }

    /** @param  (callable(Builder, ?string): Builder)|null  $constrain */
    private static function directionFilter(?callable $constrain = null): SelectFilter
    {
        $filter = SelectFilter::make('direction')
            ->label(__('filament.fields.call_direction'))
            ->options(VoipReportFilter::directionOptions());

        if ($constrain !== null) {
            $filter->query(fn (Builder $query, array $data): Builder => $constrain(
                $query,
                self::filterValue($data),
            ));
        }

        return $filter;
    }

    private static function filterValue(array $data): ?string
    {
        $value = $data['value'] ?? null;

        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    /** @return list<Indicator> */
    private static function textIndicator(string $label, mixed $value): array
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return [];
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return [];
        }

        return [Indicator::make($label.': '.$normalized)];
    }
}
