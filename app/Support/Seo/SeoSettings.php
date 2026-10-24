<?php

namespace App\Support\Seo;

use App\Models\AdminSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * Site-wide SEO settings stored as one admin setting: title suffix, default share image,
 * Twitter handle, organization info, robots.txt and per-page meta for static pages.
 */
class SeoSettings
{
    public const KEY = 'seo';

    private const CACHE_KEY = 'seo:settings';

    /** Static pages whose meta can be edited, keyed by route name. */
    public const PAGES = ['home', 'about', 'contact', 'blog'];

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn (): array => array_replace_recursive(
            self::defaults(),
            (array) AdminSetting::valueFor(self::KEY, []),
        ));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Arr::get(self::all(), $key);

        return filled($value) ? $value : $default;
    }

    /** @param  array<string, mixed>  $settings */
    public static function store(array $settings): void
    {
        AdminSetting::store(self::KEY, $settings);
        self::flush();
        SitemapCache::flush();
    }

    /** Replaces only the given top-level keys, keeping everything else that was saved. */
    public static function update(array $changes): void
    {
        self::store([...(array) AdminSetting::valueFor(self::KEY, []), ...$changes]);
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'site_name' => config('app.name'),
            'blog_title_suffix' => ' — وبلاگ '.config('app.name'),
            'default_og_image' => null,
            'twitter_site' => null,
            'organization_logo' => null,
            'same_as' => [],
            'robots_txt' => null,
            'pages' => [
                'home' => [
                    'title' => config('app.name').' — هوش تماس و تحلیل هوشمند مکالمات سازمانی',
                    'description' => 'آوایار سامانه هوش تماس برای تحلیل عمیق مکالمات پایان‌یافته؛ استخراج خودکار خلاصه مکالمه، سنجش رضایت مشتری، امتیازدهی کیفیت سرنخ، ارزیابی عملکرد کارشناسان و کوچینگ هدفمند در یک پلتفرم یکپارچه.',
                    'og_image' => null,
                ],
                'about' => [
                    'title' => 'درباره آوایار — '.config('app.name'),
                    'description' => 'آوایار با هوش مصنوعی، تماس‌های تلفنی کسب‌وکارها را به داده‌های ارزشمند تبدیل می‌کند تا هر گفتگو فرصتی برای یادگیری، بهبود و رشد باشد.',
                    'og_image' => null,
                ],
                'contact' => [
                    'title' => 'تماس با ما — '.config('app.name'),
                    'description' => 'اگر سوالی دارید یا می‌خواهید بیشتر با آوایار آشنا شوید، از فرم تماس یا راه‌های ارتباطی با ما در ارتباط باشید.',
                    'og_image' => null,
                ],
                'blog' => [
                    'title' => 'وبلاگ آوایار — '.config('app.name'),
                    'description' => 'مقالات، راهنماها و تجربیات کاربردی برای رشد کسب‌وکار با هوش مصنوعی و تحلیل مکالمات مرکز تماس.',
                    'og_image' => null,
                ],
            ],
        ];
    }

    public static function defaultRobotsTxt(): string
    {
        return implode("\n", [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /login',
            'Disallow: /app/',
            'Disallow: /workspace/',
            'Disallow: /*?q=',
            '',
            'Sitemap: '.url('/sitemap.xml'),
            '',
        ]);
    }

    public static function robotsTxt(): string
    {
        $custom = self::get('robots_txt');

        return is_string($custom) && trim($custom) !== '' ? rtrim(str_replace("\r\n", "\n", $custom))."\n" : self::defaultRobotsTxt();
    }
}
