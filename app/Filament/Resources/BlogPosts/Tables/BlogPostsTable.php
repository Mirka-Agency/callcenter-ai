<?php

namespace App\Filament\Resources\BlogPosts\Tables;

use App\Domain\Blog\BlogPostStatus;
use App\Models\BlogPost;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BlogPostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('filament.fields.title'))
                    ->searchable()
                    ->sortable()
                    ->limit(40)
                    ->formatStateUsing(function (?string $state): string {
                        if ($state === null || $state === '' || $state === BlogPost::PLACEHOLDER_TITLE) {
                            return __('filament.blog.untitled');
                        }

                        return $state;
                    }),
                TextColumn::make('slug')
                    ->label(__('filament.fields.slug'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('category.name')
                    ->label(__('filament.blog.fields.category'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('filament.blog.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (BlogPostStatus|string|null $state): string => $state instanceof BlogPostStatus
                        ? $state->label()
                        : BlogPostStatus::tryFrom((string) $state)?->label() ?? (string) $state)
                    ->color(fn (BlogPostStatus|string|null $state): string => match ($state instanceof BlogPostStatus ? $state : BlogPostStatus::tryFrom((string) $state)) {
                        BlogPostStatus::Published => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('published_at')
                    ->label(__('filament.blog.fields.published_at'))
                    ->jalaliDateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('filament.fields.created_at'))
                    ->jalaliDateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('filament.blog.fields.status'))
                    ->options(BlogPostStatus::options()),
                SelectFilter::make('category_id')
                    ->label(__('filament.blog.fields.category'))
                    ->relationship('category', 'name'),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')
                            ->label(__('filament.blog.fields.created_from')),
                        DatePicker::make('created_until')
                            ->label(__('filament.blog.fields.created_until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                Action::make('view_public')
                    ->label(__('filament.blog.actions.view_public'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn ($record) => route('blog.show', $record->slug))
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => $record->isPublished()),
                EditAction::make(),
                DeleteAction::make()->requiresConfirmation(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->requiresConfirmation(),
                ]),
            ])
            ->emptyStateHeading(__('filament.blog.empty.posts'))
            ->emptyStateDescription(__('filament.blog.empty.posts_description'));
    }
}
