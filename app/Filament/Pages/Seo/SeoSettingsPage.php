<?php

namespace App\Filament\Pages\Seo;

use App\Application\Blog\Support\BlogMediaUrl;
use App\Filament\Concerns\ManagesSeo;
use App\Support\Seo\SeoSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class SeoSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;
    use ManagesSeo;

    protected static ?string $slug = 'seo-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlassCircle;

    protected static ?int $navigationSort = 22;

    protected string $view = 'filament.pages.seo.seo-settings';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('seo.settings.navigation');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.navigation.groups.content');
    }

    public function getTitle(): string
    {
        return __('seo.settings.navigation');
    }

    public function mount(): void
    {
        $settings = SeoSettings::all();

        $this->form->fill([
            ...$settings,
            'robots_txt' => SeoSettings::robotsTxt(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewRobots')
                ->label(__('seo.settings.view_robots'))
                ->icon(Heroicon::OutlinedDocumentText)
                ->color('gray')
                ->url(url('/robots.txt'), shouldOpenInNewTab: true),
            Action::make('viewSitemap')
                ->label(__('seo.settings.view_sitemap'))
                ->icon(Heroicon::OutlinedMap)
                ->color('gray')
                ->url(url('/sitemap.xml'), shouldOpenInNewTab: true),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('seo')->tabs([
                    Tab::make(__('seo.settings.tabs.general'))->schema([
                        Section::make()->columns(2)->schema([
                            TextInput::make('site_name')->label(__('seo.settings.site_name'))->required()->maxLength(120),
                            TextInput::make('twitter_site')->label(__('seo.settings.twitter_site'))->placeholder('@avayar')->maxLength(60)
                                ->extraInputAttributes(['dir' => 'ltr']),
                            TextInput::make('blog_title_suffix')->label(__('seo.settings.blog_title_suffix'))->helperText(__('seo.settings.title_suffix_help'))->maxLength(80)->columnSpanFull(),
                            self::image('default_og_image')->label(__('seo.settings.default_og_image'))->helperText(__('seo.fields.og_image_help')),
                            self::image('organization_logo')->label(__('seo.settings.organization_logo'))->helperText(__('seo.settings.organization_logo_help')),
                            TagsInput::make('same_as')->label(__('seo.settings.same_as'))->helperText(__('seo.settings.same_as_help'))
                                ->placeholder('https://www.linkedin.com/company/...')
                                ->nestedRecursiveRules(['url:http,https', 'max:500'])
                                ->columnSpanFull(),
                        ]),
                    ]),
                    Tab::make('robots.txt')->schema([
                        Textarea::make('robots_txt')
                            ->label('robots.txt')
                            ->helperText(__('seo.settings.robots_help'))
                            ->rows(18)
                            ->maxLength(20000)
                            ->extraInputAttributes(['dir' => 'ltr', 'class' => 'font-mono text-sm'])
                            ->hintAction(
                                Action::make('resetRobots')
                                    ->label(__('seo.settings.reset_robots'))
                                    ->icon(Heroicon::OutlinedArrowPath)
                                    ->requiresConfirmation()
                                    ->action(fn ($set) => $set('robots_txt', SeoSettings::defaultRobotsTxt())),
                            ),
                    ]),
                ])->persistTabInQueryString(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $robots = trim(str_replace("\r\n", "\n", (string) ($data['robots_txt'] ?? '')));

        SeoSettings::update([
            'site_name' => $data['site_name'],
            'blog_title_suffix' => $data['blog_title_suffix'] ?? '',
            'twitter_site' => $data['twitter_site'] ?? null,
            'default_og_image' => $data['default_og_image'] ?? null,
            'organization_logo' => $data['organization_logo'] ?? null,
            'same_as' => array_values(array_filter((array) ($data['same_as'] ?? []))),
            'robots_txt' => $robots === '' || $robots === trim(SeoSettings::defaultRobotsTxt()) ? null : $robots,
        ]);

        Notification::make()->title(__('admin.common.saved'))->success()->send();

        $this->mount();
    }

    private static function image(string $name): FileUpload
    {
        return FileUpload::make($name)
            ->image()
            ->disk('public')
            ->directory('seo')
            ->visibility('public')
            ->maxSize(5120)
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'])
            ->nullable()
            ->formatStateUsing(fn (?string $state): ?string => BlogMediaUrl::toFormState($state))
            ->dehydrateStateUsing(fn (mixed $state): ?string => BlogMediaUrl::fromUploadState($state));
    }
}
