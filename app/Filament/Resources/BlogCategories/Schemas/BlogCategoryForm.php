<?php

namespace App\Filament\Resources\BlogCategories\Schemas;

use App\Application\Blog\Support\BlogMediaUrl;
use App\Filament\Support\SeoFields;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BlogCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.blog.sections.category'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('filament.fields.name'))
                            ->required()
                            ->maxLength(200)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, callable $set, callable $get): void {
                                if (filled($get('slug')) || blank($state)) {
                                    return;
                                }

                                $set('slug', str($state)->lower()->replace(' ', '-')->toString());
                            }),
                        TextInput::make('slug')
                            ->label(__('filament.fields.slug'))
                            ->required()
                            ->maxLength(200)
                            ->helperText(__('filament.blog.helpers.slug')),
                        Select::make('icon')
                            ->label(__('filament.blog.fields.icon'))
                            ->options([
                                'cpu' => 'cpu',
                                'headset' => 'headset',
                                'chart' => 'chart',
                                'user' => 'user',
                                'users' => 'users',
                                'doc' => 'doc',
                                'grid' => 'grid',
                            ])
                            ->searchable()
                            ->nullable(),
                        RichEditor::make('description')
                            ->label(__('filament.fields.description'))
                            ->columnSpanFull()
                            ->toolbarButtons([
                                ['bold', 'italic', 'underline', 'link'],
                                ['bulletList', 'orderedList'],
                                ['undo', 'redo'],
                            ]),
                        FileUpload::make('image_url')
                            ->label(__('filament.blog.fields.image'))
                            ->image()
                            ->disk('public')
                            ->directory('blog/categories')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                            ->nullable()
                            ->formatStateUsing(fn (?string $state): ?string => BlogMediaUrl::toFormState($state))
                            ->dehydrateStateUsing(fn (mixed $state): ?string => BlogMediaUrl::fromUploadState($state)),
                    ])
                    ->columns(2),

                SeoFields::section('name', 'description', fn (?string $slug): string => route('blog.category', filled($slug) ? $slug : 'slug'), 'blog/categories/og'),

                SeoFields::faqSection(),
            ]);
    }
}
