<?php

namespace App\Filament\Resources\ConsultationRequests;

use App\Filament\Concerns\OnlySuperAdmin;
use App\Filament\Resources\ConsultationRequests\Pages\ListConsultationRequests;
use App\Filament\Resources\ConsultationRequests\Pages\ViewConsultationRequest;
use App\Filament\Resources\ConsultationRequests\Tables\ConsultationRequestsTable;
use App\Models\ConsultationRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ConsultationRequestResource extends Resource
{
    use OnlySuperAdmin;

    protected static ?string $model = ConsultationRequest::class;

    protected static ?string $slug = 'consultation-requests';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.consultation_requests');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('filament.navigation.groups.organizations');
    }

    public static function getModelLabel(): string
    {
        return __('filament.models.consultation_request');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.models.consultation_requests');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = ConsultationRequest::query()->active()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return ConsultationRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConsultationRequests::route('/'),
            'view' => ViewConsultationRequest::route('/{record}'),
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
