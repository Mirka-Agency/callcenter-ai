<?php

namespace App\Filament\Pages\Seo;

use App\Application\Blog\Support\BlogMediaUrl;
use App\Filament\Concerns\ManagesSeo;
use App\Filament\Support\SeoFields;
use App\Support\Seo\SeoSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Meta title, description and social tags for the landing pages that are not blog content.
 * Empty fields fall back to the built-in defaults from SeoSettings::defaults().
 */
class StaticPagesSeo extends Page implements HasForms
{
    use InteractsWithForms;
    use ManagesSeo;

    private const FIELDS = ['title', 'description', 'og_title', 'og_description', 'og_image', 'noindex'];

    protected static ?string $slug = 'seo-static-pages';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static ?int $navigationSort = 21;

    protected string $view = 'filament.pages.seo.static-pages';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('seo.static_pages.navigation');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.navigation.groups.content');
    }

    public function getTitle(): string
    {
        return __('seo.static_pages.navigation');
    }

    public function getSubheading(): string
    {
        return __('seo.static_pages.subheading');
    }

    public function mount(): void
    {
        $saved = (array) SeoSettings::get('pages', []);
        $defaults = SeoSettings::defaults()['pages'];

        $this->form->fill(['pages' => collect(SeoSettings::PAGES)->mapWithKeys(fn (string $page): array => [$page => [
            'title' => ($saved[$page]['title'] ?? null) === $defaults[$page]['title'] ? null : ($saved[$page]['title'] ?? null),
            'description' => ($saved[$page]['description'] ?? null) === $defaults[$page]['description'] ? null : ($saved[$page]['description'] ?? null),
            'og_title' => $saved[$page]['og_title'] ?? null,
            'og_description' => $saved[$page]['og_description'] ?? null,
            'og_image' => $saved[$page]['og_image'] ?? null,
            'noindex' => (bool) ($saved[$page]['noindex'] ?? false),
        ]])->all()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(collect(SeoSettings::PAGES)->map(fn (string $page): Section => $this->pageSection($page))->all())
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        SeoSettings::update([
            'pages' => collect(SeoSettings::PAGES)->mapWithKeys(fn (string $page): array => [$page => collect(self::FIELDS)
                ->mapWithKeys(fn (string $field): array => [$field => $field === 'noindex'
                    ? (bool) ($data['pages'][$page][$field] ?? false)
                    : (filled($value = trim((string) ($data['pages'][$page][$field] ?? ''))) ? $value : null)])
                ->all()])->all(),
        ]);

        Notification::make()->title(__('admin.common.saved'))->success()->send();

        $this->mount();
    }

    private function pageSection(string $page): Section
    {
        $defaults = SeoSettings::defaults()['pages'][$page];
        $url = route($page);

        return Section::make(__('seo.settings.pages.'.$page))
            ->description(urldecode($url))
            ->statePath('pages.'.$page)
            ->collapsible()
            ->columns(2)
            ->headerActions([
                Action::make('open_'.$page)
                    ->label(__('seo.static_pages.open'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->link()
                    ->url($url, shouldOpenInNewTab: true),
                Action::make('reset_'.$page)
                    ->label(__('seo.static_pages.reset'))
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('gray')
                    ->link()
                    ->requiresConfirmation()
                    ->action(function (Set $set) use ($page): void {
                        foreach (self::FIELDS as $field) {
                            $set('pages.'.$page.'.'.$field, $field === 'noindex' ? false : null, isAbsolute: true);
                        }
                    }),
            ])
            ->schema([
                View::make('filament.seo.snippet-preview')
                    ->viewData(fn (Get $get): array => [
                        'title' => filled($get('title')) ? $get('title') : $defaults['title'],
                        'description' => filled($get('description')) ? $get('description') : $defaults['description'],
                        'url' => $url,
                    ])
                    ->columnSpanFull(),
                TextInput::make('title')
                    ->label(__('filament.blog.fields.meta_title'))
                    ->placeholder($defaults['title'])
                    ->maxLength(300)
                    ->live(debounce: 600)
                    ->helperText(fn (?string $state): string => SeoFields::counter($state ?: $defaults['title'], SeoFields::TITLE_LIMIT, __('seo.static_pages.title_help')))
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label(__('filament.blog.fields.meta_description'))
                    ->placeholder($defaults['description'])
                    ->rows(3)
                    ->maxLength(1000)
                    ->live(debounce: 600)
                    ->helperText(fn (?string $state): string => SeoFields::counter($state ?: $defaults['description'], SeoFields::DESCRIPTION_LIMIT, __('seo.static_pages.description_help')))
                    ->columnSpanFull(),
                TextInput::make('og_title')
                    ->label(__('filament.blog.fields.og_title'))
                    ->placeholder(fn (Get $get): string => filled($get('title')) ? $get('title') : $defaults['title'])
                    ->maxLength(300),
                Textarea::make('og_description')
                    ->label(__('filament.blog.fields.og_description'))
                    ->placeholder(fn (Get $get): string => filled($get('description')) ? $get('description') : $defaults['description'])
                    ->rows(2)
                    ->maxLength(1000),
                FileUpload::make('og_image')
                    ->label(__('filament.blog.fields.og_image'))
                    ->helperText(__('seo.settings.page_og_help'))
                    ->image()
                    ->disk('public')
                    ->directory('seo')
                    ->visibility('public')
                    ->maxSize(5120)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                    ->nullable()
                    ->formatStateUsing(fn (?string $state): ?string => BlogMediaUrl::toFormState($state))
                    ->dehydrateStateUsing(fn (mixed $state): ?string => BlogMediaUrl::fromUploadState($state)),
                Toggle::make('noindex')
                    ->label(__('seo.fields.noindex'))
                    ->helperText(__('seo.static_pages.noindex_help')),
            ]);
    }
}
