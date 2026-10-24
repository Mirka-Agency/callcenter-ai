<?php

namespace App\Filament\Resources\Announcements;

use App\Domain\Platform\Enums\AdminArea;
use App\Domain\Platform\Enums\AnnouncementAudience;
use App\Domain\Platform\Enums\AnnouncementLevel;
use App\Filament\Concerns\RequiresAdminArea;
use App\Filament\Resources\Announcements\Pages\ManageAnnouncements;
use App\Models\Announcement;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AnnouncementResource extends Resource
{
    use RequiresAdminArea;

    protected static ?string $model = Announcement::class;

    protected static ?string $slug = 'announcements';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?int $navigationSort = 5;

    public static function adminArea(): AdminArea
    {
        return AdminArea::Support;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.announcements');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('filament.navigation.groups.support');
    }

    public static function getModelLabel(): string
    {
        return __('admin.models.announcement');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.navigation.announcements');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('title')->label(__('admin.announcements.title'))->required()->maxLength(255)->columnSpanFull(),
            Textarea::make('body')->label(__('admin.announcements.body'))->required()->rows(4)->maxLength(2000)->columnSpanFull(),
            Select::make('level')->label(__('admin.announcements.level'))->options(AnnouncementLevel::options())->default(AnnouncementLevel::Info->value)->required()->native(false),
            Select::make('audience')->label(__('admin.announcements.audience'))->options(AnnouncementAudience::options())->default(AnnouncementAudience::All->value)->required()->native(false),
            DateTimePicker::make('starts_at')->label(__('admin.announcements.starts_at'))->jalali(),
            DateTimePicker::make('ends_at')->label(__('admin.announcements.ends_at'))->jalali()->after('starts_at'),
            Toggle::make('is_active')->label(__('admin.billing.is_active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label(__('admin.announcements.title'))->searchable()->wrap(),
                TextColumn::make('level')->label(__('admin.announcements.level'))->badge()
                    ->formatStateUsing(fn (AnnouncementLevel $state): string => $state->label())
                    ->color(fn (AnnouncementLevel $state): string => $state->color()),
                TextColumn::make('audience')->label(__('admin.announcements.audience'))->formatStateUsing(fn (AnnouncementAudience $state): string => $state->label()),
                TextColumn::make('starts_at')->label(__('admin.announcements.starts_at'))->jalaliDateTime()->placeholder('—'),
                TextColumn::make('ends_at')->label(__('admin.announcements.ends_at'))->jalaliDateTime()->placeholder('—'),
                IconColumn::make('is_active')->label(__('admin.billing.is_active'))->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAnnouncements::route('/'),
        ];
    }
}
