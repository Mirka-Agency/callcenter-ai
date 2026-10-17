<?php

namespace App\Filament\Resources\DemoRequests;

use App\Filament\Concerns\OnlySuperAdmin;
use App\Filament\Resources\DemoRequests\Pages\ListDemoRequests;
use App\Filament\Resources\DemoRequests\Pages\ViewDemoRequest;
use App\Filament\Resources\DemoRequests\Tables\DemoRequestsTable;
use App\Models\DemoRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class DemoRequestResource extends Resource
{
    use OnlySuperAdmin;

    protected static ?string $model = DemoRequest::class;

    protected static ?string $slug = 'demo-requests';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.demo_requests');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('filament.navigation.groups.organizations');
    }

    public static function getModelLabel(): string
    {
        return __('filament.models.demo_request');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.models.demo_requests');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = DemoRequest::query()->active()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return DemoRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDemoRequests::route('/'),
            'view' => ViewDemoRequest::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canView(Model $record): bool
    {
        return static::canAccess();
    }
}
