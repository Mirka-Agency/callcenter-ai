<?php

namespace App\Filament\Resources\BlogPosts\Schemas;

use App\Application\Blog\Support\BlogMediaUrl;
use App\Enums\UserRole;
use App\Models\BlogCategory;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class BlogPostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.blog.autosave-status')
                    ->columnSpanFull(),

                Section::make(__('filament.blog.sections.content'))
                    ->schema([
                        TextInput::make('title')
                            ->label(__('filament.fields.title'))
                            ->maxLength(300)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, callable $set, callable $get): void {
                                if (filled($get('slug')) || blank($state)) {
                                    return;
                                }

                                $set('slug', str($state)->lower()->replace([' ', '‌'], '-')->toString());
                            }),
                        TextInput::make('slug')
                            ->label(__('filament.fields.slug'))
                            ->maxLength(200)
                            ->helperText(__('filament.blog.helpers.slug')),
                        Textarea::make('excerpt')
                            ->label(__('filament.blog.fields.excerpt'))
                            ->rows(3)
                            ->maxLength(5000)
                            ->columnSpanFull(),
                        RichEditor::make('body')
                            ->label(__('filament.blog.fields.body'))
                            ->columnSpanFull()
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('blog/body')
                            ->fileAttachmentsVisibility('public')
                            ->toolbarButtons([
                                ['bold', 'italic', 'underline', 'strike', 'link'],
                                ['h2', 'h3'],
                                ['alignStart', 'alignCenter', 'alignEnd'],
                                ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
                                ['table', 'attachFiles'],
                                ['undo', 'redo'],
                            ]),
                        Hidden::make('content_version'),
                    ])
                    ->columnSpan(['lg' => 2]),

                Section::make(__('filament.blog.sections.publish'))
                    ->schema([
                        Toggle::make('publish')
                            ->label(__('filament.blog.fields.publish'))
                            ->helperText(__('filament.blog.helpers.publish'))
                            ->live(),
                        DateTimePicker::make('published_at')
                            ->label(__('filament.blog.fields.published_at'))
                            ->seconds(false)
                            ->visible(fn (callable $get): bool => (bool) $get('publish')),
                        Select::make('category_id')
                            ->label(__('filament.blog.fields.category'))
                            ->options(fn () => BlogCategory::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->nullable(),
                        Select::make('author_user_id')
                            ->label(__('filament.blog.fields.author'))
                            ->options(fn () => User::query()
                                ->whereIn('role', [UserRole::SuperAdmin->value, UserRole::Admin->value])
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->nullable()
                            ->live()
                            ->afterStateUpdated(function (?int $state, callable $set): void {
                                if (! $state) {
                                    return;
                                }

                                $name = User::query()->whereKey($state)->value('name');
                                if (is_string($name) && $name !== '') {
                                    $set('author_display_name', $name);
                                }
                            }),
                        TextInput::make('author_display_name')
                            ->label(__('filament.blog.fields.author_display_name'))
                            ->maxLength(255),
                        FileUpload::make('cover_image_url')
                            ->label(__('filament.blog.fields.cover_image'))
                            ->image()
                            ->disk('public')
                            ->directory('blog/covers')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                            ->nullable()
                            ->formatStateUsing(fn (?string $state): ?string => BlogMediaUrl::toFormState($state))
                            ->dehydrateStateUsing(fn (mixed $state): ?string => BlogMediaUrl::fromUploadState($state)),
                        TextInput::make('cover_video_url')
                            ->label(__('filament.blog.fields.cover_video'))
                            ->url()
                            ->maxLength(1000)
                            ->helperText(__('filament.blog.helpers.cover_video')),
                    ])
                    ->columnSpan(['lg' => 1]),

                Section::make(__('filament.blog.sections.seo'))
                    ->schema([
                        TextInput::make('meta_title')
                            ->label(__('filament.blog.fields.meta_title'))
                            ->maxLength(300),
                        Textarea::make('meta_description')
                            ->label(__('filament.blog.fields.meta_description'))
                            ->rows(3)
                            ->maxLength(5000),
                        TextInput::make('seo_keywords')
                            ->label(__('filament.blog.fields.seo_keywords'))
                            ->maxLength(1000),
                        TextInput::make('canonical_url')
                            ->label(__('filament.blog.fields.canonical_url'))
                            ->maxLength(1000),
                        TextInput::make('og_title')
                            ->label(__('filament.blog.fields.og_title'))
                            ->maxLength(300),
                        Textarea::make('og_description')
                            ->label(__('filament.blog.fields.og_description'))
                            ->rows(2)
                            ->maxLength(5000),
                        FileUpload::make('og_image_url')
                            ->label(__('filament.blog.fields.og_image'))
                            ->image()
                            ->disk('public')
                            ->directory('blog/og')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                            ->nullable()
                            ->formatStateUsing(fn (?string $state): ?string => BlogMediaUrl::toFormState($state))
                            ->dehydrateStateUsing(fn (mixed $state): ?string => BlogMediaUrl::fromUploadState($state)),
                    ])
                    ->columns(2)
                    ->collapsed()
                    ->columnSpanFull(),

                Section::make(__('filament.blog.sections.faq'))
                    ->schema([
                        Repeater::make('faq_json')
                            ->label(__('filament.blog.fields.faq'))
                            ->schema([
                                TextInput::make('question')
                                    ->label(__('filament.blog.fields.faq_question'))
                                    ->required()
                                    ->maxLength(500),
                                Textarea::make('answer')
                                    ->label(__('filament.blog.fields.faq_answer'))
                                    ->required()
                                    ->rows(3)
                                    ->maxLength(5000),
                            ])
                            ->default([])
                            ->maxItems(50)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['question'] ?? null)
                            ->columnSpanFull(),
                    ])
                    ->collapsed()
                    ->columnSpanFull(),
            ])
            ->columns(3);
    }
}
