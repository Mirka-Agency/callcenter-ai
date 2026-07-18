<?php

namespace App\Filament\Resources\PendingQueueJobs;

use App\Filament\Concerns\OnlySuperAdmin;
use App\Filament\Resources\PendingQueueJobs\Pages\ListPendingQueueJobs;
use App\Filament\Resources\PendingQueueJobs\Pages\ViewPendingQueueJob;
use App\Filament\Resources\PendingQueueJobs\Tables\PendingQueueJobsTable;
use App\Models\PendingQueueJob;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PendingQueueJobResource extends Resource
{
    use OnlySuperAdmin;

    protected static ?string $model = PendingQueueJob::class;

    protected static ?string $slug = 'pending-queue-jobs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.pending_queue_jobs');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('filament.navigation.groups.system');
    }

    public static function getModelLabel(): string
    {
        return __('filament.models.pending_queue_job');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.models.pending_queue_jobs');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = PendingQueueJob::query()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return PendingQueueJob::query()->exists() ? 'warning' : 'gray';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return PendingQueueJobsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPendingQueueJobs::route('/'),
            'view' => ViewPendingQueueJob::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return static::canAccess();
    }

    public static function canDeleteAny(): bool
    {
        return static::canAccess();
    }
}
