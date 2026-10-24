<?php

namespace App\Filament\Support;

use App\Application\Blog\Support\BlogMediaUrl;
use App\Support\Seo\SeoSettings;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;

/**
 * SEO and FAQ form sections shared by blog posts and categories.
 * Empty meta title / description fall back to the title / summary on the public page.
 */
class SeoFields
{
    public const TITLE_LIMIT = 60;

    public const DESCRIPTION_LIMIT = 160;

    /**
     * @param  string  $titleField  form field used when the meta title is empty
     * @param  string|null  $summaryField  form field used when the meta description is empty
     * @param  Closure(string|null): string  $urlForSlug
     */
    public static function section(string $titleField, ?string $summaryField, Closure $urlForSlug, string $imageDirectory, bool $withKeywords = false): Section
    {
        return Section::make(__('filament.blog.sections.seo'))
            ->description(__('seo.fields.section_help'))
            ->schema([
                View::make('filament.seo.snippet-preview')
                    ->viewData(fn (Get $get): array => [
                        'title' => (filled($get('meta_title')) ? $get('meta_title') : ($get($titleField) ?: '—')).(SeoSettings::all()['blog_title_suffix'] ?? ''),
                        'description' => filled($get('meta_description')) ? $get('meta_description') : strip_tags((string) ($summaryField ? $get($summaryField) : '')),
                        'url' => $urlForSlug($get('slug')),
                    ])
                    ->columnSpanFull(),
                TextInput::make('meta_title')
                    ->label(__('filament.blog.fields.meta_title'))
                    ->maxLength(300)
                    ->placeholder(fn (Get $get): ?string => $get($titleField))
                    ->helperText(fn (?string $state, Get $get): string => self::counter($state ?: $get($titleField), self::TITLE_LIMIT, __('seo.fields.meta_title_help')))
                    ->live(debounce: 600)
                    ->columnSpanFull(),
                Textarea::make('meta_description')
                    ->label(__('filament.blog.fields.meta_description'))
                    ->rows(3)
                    ->maxLength(5000)
                    ->placeholder(fn (Get $get): ?string => $summaryField ? strip_tags((string) $get($summaryField)) : null)
                    ->helperText(fn (?string $state): string => self::counter($state, self::DESCRIPTION_LIMIT, __('seo.fields.meta_description_help')))
                    ->live(debounce: 600)
                    ->columnSpanFull(),
                ...($withKeywords ? [
                    TextInput::make('seo_keywords')
                        ->label(__('filament.blog.fields.seo_keywords'))
                        ->maxLength(1000),
                ] : []),
                TextInput::make('canonical_url')
                    ->label(__('filament.blog.fields.canonical_url'))
                    ->helperText(__('seo.fields.canonical_help'))
                    ->maxLength(1000),
                Toggle::make('noindex')
                    ->label(__('seo.fields.noindex'))
                    ->helperText(__('seo.fields.noindex_help')),
                TextInput::make('og_title')
                    ->label(__('filament.blog.fields.og_title'))
                    ->placeholder(fn (Get $get): ?string => filled($get('meta_title')) ? $get('meta_title') : $get($titleField))
                    ->maxLength(300),
                Textarea::make('og_description')
                    ->label(__('filament.blog.fields.og_description'))
                    ->placeholder(fn (Get $get): ?string => $get('meta_description'))
                    ->rows(2)
                    ->maxLength(5000),
                FileUpload::make('og_image_url')
                    ->label(__('filament.blog.fields.og_image'))
                    ->helperText(__('seo.fields.og_image_help'))
                    ->image()
                    ->disk('public')
                    ->directory($imageDirectory)
                    ->visibility('public')
                    ->maxSize(5120)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                    ->nullable()
                    ->formatStateUsing(fn (?string $state): ?string => BlogMediaUrl::toFormState($state))
                    ->dehydrateStateUsing(fn (mixed $state): ?string => BlogMediaUrl::fromUploadState($state))
                    ->columnSpanFull(),
            ])
            ->columns(2)
            ->collapsible()
            ->columnSpanFull();
    }

    public static function faqSection(): Section
    {
        return Section::make(__('filament.blog.sections.faq'))
            ->description(__('seo.fields.faq_help'))
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
            ->columnSpanFull();
    }

    public static function counter(?string $value, int $limit, string $help): string
    {
        $length = mb_strlen(trim(strip_tags((string) $value)));

        return __('seo.fields.counter', ['count' => $length, 'limit' => $limit]).($length > $limit ? ' — '.__('seo.fields.too_long') : '').' · '.$help;
    }
}
