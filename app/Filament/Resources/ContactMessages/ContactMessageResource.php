<?php

namespace App\Filament\Resources\ContactMessages;

use App\Domain\Platform\Enums\AdminArea;
use App\Filament\Concerns\RequiresAdminArea;
use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use App\Filament\Resources\ContactMessages\Tables\ContactMessagesTable;
use App\Models\ContactMessage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ContactMessageResource extends Resource
{
    use RequiresAdminArea;

    protected static ?string $model = ContactMessage::class;

    protected static ?string $recordTitleAttribute = 'full_name';

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['full_name', 'email', 'subject'];
    }

    public static function adminArea(): AdminArea
    {
        return AdminArea::Sales;
    }

    protected static ?string $slug = 'contact-messages';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.contact_messages');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('filament.navigation.groups.sales');
    }

    public static function getModelLabel(): string
    {
        return __('filament.models.contact_message');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.models.contact_messages');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = ContactMessage::query()->active()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return ContactMessagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactMessages::route('/'),
            'view' => ViewContactMessage::route('/{record}'),
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

    public static function canEdit(Model $record): bool
    {
        return false;
    }
}
