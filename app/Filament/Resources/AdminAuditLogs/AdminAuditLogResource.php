<?php

namespace App\Filament\Resources\AdminAuditLogs;

use App\Filament\Concerns\OnlySuperAdmin;
use App\Filament\Resources\AdminAuditLogs\Pages\ListAdminAuditLogs;
use App\Models\AdminAuditLog;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AdminAuditLogResource extends Resource
{
    use OnlySuperAdmin;

    protected static ?string $model = AdminAuditLog::class;

    protected static ?string $slug = 'admin-audit-logs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.audit_logs');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('filament.navigation.groups.security');
    }

    public static function getModelLabel(): string
    {
        return __('admin.audit.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.navigation.audit_logs');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('user.name')->label(__('admin.audit.user')),
            TextEntry::make('action')->label(__('admin.audit.action'))
                ->formatStateUsing(fn (string $state): string => __('admin.audit.actions.'.$state)),
            TextEntry::make('auditable_label')->label(__('admin.audit.subject')),
            TextEntry::make('ip_address')->label(__('admin.audit.ip')),
            TextEntry::make('created_at')->label(__('filament.fields.created_at'))->jalaliDateTime(),
            KeyValueEntry::make('changes.before')->label(__('admin.audit.before'))->columnSpanFull()
                ->state(fn (AdminAuditLog $record): array => self::flatten($record->changes['before'] ?? [])),
            KeyValueEntry::make('changes.after')->label(__('admin.audit.after'))->columnSpanFull()
                ->state(fn (AdminAuditLog $record): array => self::flatten($record->changes['after'] ?? [])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('user'))
            ->columns([
                TextColumn::make('created_at')->label(__('filament.fields.created_at'))->jalaliDateTime()->sortable(),
                TextColumn::make('user.name')->label(__('admin.audit.user'))->searchable(),
                TextColumn::make('action')->label(__('admin.audit.action'))->badge()
                    ->formatStateUsing(fn (string $state): string => __('admin.audit.actions.'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('auditable_label')->label(__('admin.audit.subject'))->searchable()->wrap(),
                TextColumn::make('ip_address')->label(__('admin.audit.ip'))->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('user_id')->label(__('admin.audit.user'))
                    ->options(fn (): array => User::query()->whereIn('role', ['super_admin', 'admin'])->pluck('name', 'id')->all()),
                SelectFilter::make('action')->label(__('admin.audit.action'))
                    ->options(__('admin.audit.actions')),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('created_at', 'desc')
            ->persistFiltersInSession()
            ->paginated([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdminAuditLogs::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /** @return array<string, string> */
    private static function flatten(array $values): array
    {
        return collect($values)
            ->map(fn (mixed $value): string => is_scalar($value) || $value === null
                ? (string) ($value ?? '—')
                : json_encode($value, JSON_UNESCAPED_UNICODE))
            ->all();
    }
}
