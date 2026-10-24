<?php

namespace App\Filament\Resources\NpsResponses;

use App\Domain\Platform\Enums\AdminArea;
use App\Filament\Concerns\RequiresAdminArea;
use App\Filament\Resources\NpsResponses\Pages\ManageNpsResponses;
use App\Filament\Resources\Organizations\OrganizationResource;
use App\Filament\Support\CsvExport;
use App\Models\NpsResponse;
use App\Services\PlatformAnalytics\NpsMetrics;
use App\Support\JalaliDate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NpsResponseResource extends Resource
{
    use RequiresAdminArea;

    protected static ?string $model = NpsResponse::class;

    protected static ?string $slug = 'nps-responses';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFaceSmile;

    protected static ?int $navigationSort = 3;

    public static function adminArea(): AdminArea
    {
        return AdminArea::Analytics;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.nps');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('filament.navigation.groups.analytics');
    }

    public static function getModelLabel(): string
    {
        return __('admin.models.nps_response');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.navigation.nps');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['organization:id,title', 'user:id,name']))
            ->columns([
                TextColumn::make('created_at')->label(__('admin.common.date'))->jalaliDateTime()->sortable(),
                TextColumn::make('organization.title')->label(__('filament.models.organization'))->searchable()
                    ->url(fn (NpsResponse $record): ?string => $record->organization_id ? OrganizationResource::getUrl('overview', ['record' => $record->organization_id]) : null),
                TextColumn::make('user.name')->label(__('admin.common.user')),
                TextColumn::make('score')->label(__('admin.nps.score_column'))->badge()->sortable()
                    ->formatStateUsing(fn (?int $state): string => $state === null ? __('admin.nps.skipped') : JalaliDate::persianDigits($state))
                    ->color(fn (?int $state): string => match (NpsMetrics::category($state)) {
                        'promoter' => 'success',
                        'passive' => 'warning',
                        'detractor' => 'danger',
                        default => 'gray',
                    })
                    ->placeholder(__('admin.nps.skipped')),
                TextColumn::make('comment')->label(__('admin.nps.comment'))->wrap()->limit(200)->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('category')->label(__('admin.nps.category'))
                    ->options([
                        'promoter' => __('admin.nps.promoters'),
                        'passive' => __('admin.nps.passives'),
                        'detractor' => __('admin.nps.detractors'),
                        'skipped' => __('admin.nps.skipped'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'promoter' => $query->where('score', '>=', 9),
                        'passive' => $query->whereBetween('score', [7, 8]),
                        'detractor' => $query->where('score', '<=', 6),
                        'skipped' => $query->whereNull('score'),
                        default => $query,
                    }),
            ])
            ->headerActions([
                CsvExport::action('nps', fn (): iterable => self::exportRows()),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /** @return iterable<array<string, mixed>> */
    public static function exportRows(): iterable
    {
        foreach (NpsResponse::query()->with(['organization:id,title', 'user:id,name'])->lazyById() as $response) {
            yield [
                __('admin.common.date') => JalaliDate::datetime($response->created_at),
                __('filament.models.organization') => $response->organization?->title,
                __('admin.common.user') => $response->user?->name,
                __('admin.nps.score_column') => $response->score,
                __('admin.nps.comment') => $response->comment,
            ];
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageNpsResponses::route('/'),
        ];
    }
}
