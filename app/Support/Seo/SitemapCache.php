<?php

namespace App\Support\Seo;

use Illuminate\Support\Facades\Cache;

class SitemapCache
{
    public const TTL_MINUTES = 60;

    public const SECTIONS = ['index', 'pages', 'categories', 'posts'];

    public static function remember(string $section, \Closure $build): string
    {
        return Cache::remember(self::key($section), now()->addMinutes(self::TTL_MINUTES), $build);
    }

    public static function flush(): void
    {
        foreach (self::SECTIONS as $section) {
            Cache::forget(self::key($section));
        }
    }

    private static function key(string $section): string
    {
        return 'seo:sitemap:'.$section.':'.request()?->getHost();
    }
}
