<?php

namespace App\Filament\Resources\SeoRedirects;

use App\Domain\Seo\RedirectStatus;
use App\Filament\Concerns\ManagesSeo;
use App\Filament\Resources\SeoRedirects\Pages\ManageSeoRedirects;
use App\Models\SeoRedirect;
use App\Support\Seo\RedirectResolver;
use BackedEnum;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class SeoRedirectResource extends Resource
{
    use ManagesSeo;

    protected static ?string $model = SeoRedirect::class;

    protected static ?string $slug = 'seo-redirects';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnRight;

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'source_path';

    public static function getNavigationLabel(): string
    {
        return __('seo.redirects.navigation');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.navigation.groups.content');
    }

    public static function getModelLabel(): string
    {
        return __('seo.redirects.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('seo.redirects.navigation');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('source_path')
                ->label(__('seo.redirects.source'))
                ->helperText(__('seo.redirects.source_help'))
                ->placeholder('/blog/old-post')
                ->required()
                ->maxLength(1000)
                ->extraInputAttributes(['dir' => 'ltr'])
                ->dehydrateStateUsing(fn (?string $state): string => RedirectResolver::normalizePath((string) $state))
                ->rules([
                    fn (Get $get, ?SeoRedirect $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                        $exists = SeoRedirect::query()
                            ->where('source_path', RedirectResolver::normalizePath((string) $value))
                            ->where('match_type', $get('match_type') ?: SeoRedirect::EXACT)
                            ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                            ->exists();

                        if ($exists) {
                            $fail(__('seo.redirects.duplicate'));
                        }
                    },
                ])
                ->columnSpanFull(),
            Radio::make('match_type')
                ->label(__('seo.redirects.match_type'))
                ->options([
                    SeoRedirect::EXACT => __('seo.redirects.match.exact'),
                    SeoRedirect::PREFIX => __('seo.redirects.match.prefix'),
                ])
                ->descriptions([
                    SeoRedirect::EXACT => __('seo.redirects.match.exact_help'),
                    SeoRedirect::PREFIX => __('seo.redirects.match.prefix_help'),
                ])
                ->default(SeoRedirect::EXACT)
                ->required(),
            Select::make('status_code')
                ->label(__('seo.redirects.status'))
                ->options(RedirectStatus::options())
                ->default(RedirectStatus::MovedPermanently->value)
                ->native(false)
                ->required()
                ->live(),
            TextInput::make('target_url')
                ->label(__('seo.redirects.target'))
                ->helperText(__('seo.redirects.target_help'))
                ->placeholder('/blog/new-post')
                ->maxLength(1000)
                ->extraInputAttributes(['dir' => 'ltr'])
                ->visible(fn (Get $get): bool => self::statusFrom($get('status_code'))?->needsTarget() ?? true)
                ->required(fn (Get $get): bool => self::statusFrom($get('status_code'))?->needsTarget() ?? true)
                ->rules([
                    'regex:#^(/|https?://)#i',
                    fn (Get $get, ?SeoRedirect $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                        try {
                            app(RedirectResolver::class)->assertNoLoop(
                                (string) $get('source_path'),
                                $get('match_type') ?: SeoRedirect::EXACT,
                                filled($value) ? (string) $value : null,
                                $record?->getKey(),
                            );
                        } catch (ValidationException $exception) {
                            $fail($exception->getMessage());
                        }
                    },
                ])
                ->validationMessages(['regex' => __('seo.redirects.target_format')])
                ->columnSpanFull(),
            Toggle::make('is_active')
                ->label(__('seo.redirects.active'))
                ->default(true),
            Textarea::make('notes')
                ->label(__('seo.redirects.notes'))
                ->rows(2)
                ->maxLength(2000)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source_path')
                    ->label(__('seo.redirects.source'))
                    ->formatStateUsing(fn (string $state, SeoRedirect $record): string => $record->match_type === SeoRedirect::PREFIX ? rtrim($state, '/').'/*' : $state)
                    ->searchable()
                    ->copyable()
                    ->extraAttributes(['dir' => 'ltr']),
                TextColumn::make('status_code')
                    ->label(__('seo.redirects.status'))
                    ->badge()
                    ->formatStateUsing(fn (RedirectStatus $state): string => (string) $state->value)
                    ->tooltip(fn (SeoRedirect $record): string => $record->status_code->label())
                    ->color(fn (RedirectStatus $state): string => $state === RedirectStatus::Gone ? 'danger' : ($state->isPermanent() ? 'success' : 'warning')),
                TextColumn::make('target_url')
                    ->label(__('seo.redirects.target'))
                    ->placeholder('—')
                    ->searchable()
                    ->limit(60)
                    ->extraAttributes(['dir' => 'ltr']),
                ToggleColumn::make('is_active')->label(__('seo.redirects.active')),
                TextColumn::make('hits')->label(__('seo.redirects.hits'))->numeric()->sortable(),
                TextColumn::make('last_hit_at')->label(__('seo.redirects.last_hit'))->jalaliDateTime()->placeholder('—')->sortable(),
                TextColumn::make('notes')->label(__('seo.redirects.notes'))->limit(40)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('creator.name')->label(__('admin.common.user'))->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label(__('admin.common.date'))->jalaliDateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status_code')->label(__('seo.redirects.status'))->options(RedirectStatus::options()),
                SelectFilter::make('match_type')->label(__('seo.redirects.match_type'))->options([
                    SeoRedirect::EXACT => __('seo.redirects.match.exact'),
                    SeoRedirect::PREFIX => __('seo.redirects.match.prefix'),
                ]),
                TernaryFilter::make('is_active')->label(__('seo.redirects.active')),
            ])
            ->recordActions([
                EditAction::make()->after(fn (SeoRedirect $record) => self::afterSave($record)),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /** Re-points rules that redirect to this rule's source, so visitors never follow a chain. */
    public static function afterSave(SeoRedirect $redirect): void
    {
        if ($redirect->is_active && $redirect->match_type === SeoRedirect::EXACT) {
            app(RedirectResolver::class)->flattenChainsTo($redirect->source_path, $redirect->target_url, $redirect->id);
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSeoRedirects::route('/'),
        ];
    }

    private static function statusFrom(mixed $value): ?RedirectStatus
    {
        return $value instanceof RedirectStatus ? $value : RedirectStatus::tryFrom((int) $value);
    }
}
