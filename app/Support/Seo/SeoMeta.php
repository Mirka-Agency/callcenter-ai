<?php

namespace App\Support\Seo;

use Illuminate\Support\Str;

/**
 * Resolves page SEO input (title, description, canonical, robots, Open Graph, Twitter,
 * article dates and JSON-LD) into the final tags rendered by components/seo/head.
 */
class SeoMeta
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function resolve(array $input): array
    {
        $title = trim((string) ($input['title'] ?? config('app.name')));
        $description = Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($input['description'] ?? '')))), 300, '…');
        $canonical = $input['canonical'] ?? request()->url();
        $image = self::absoluteUrl($input['og_image'] ?? null) ?? self::absoluteUrl(SeoSettings::get('default_og_image'));
        $ogTitle = filled($input['og_title'] ?? null) ? (string) $input['og_title'] : $title;
        $ogDescription = filled($input['og_description'] ?? null) ? (string) $input['og_description'] : $description;
        $twitterSite = self::twitterHandle(SeoSettings::get('twitter_site'));

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => $input['robots'] ?? (($input['noindex'] ?? false) ? 'noindex, follow' : 'index, follow, max-image-preview:large'),
            'og' => array_filter([
                'og:type' => $input['og_type'] ?? 'website',
                'og:locale' => 'fa_IR',
                'og:site_name' => SeoSettings::get('site_name', config('app.name')),
                'og:title' => $ogTitle,
                'og:description' => $ogDescription,
                'og:url' => $canonical,
                'og:image' => $image,
                'og:image:alt' => $image ? $ogTitle : null,
                'article:published_time' => $input['published_time'] ?? null,
                'article:modified_time' => $input['modified_time'] ?? null,
                'article:section' => $input['section'] ?? null,
                'article:author' => $input['author'] ?? null,
            ], fn ($value): bool => filled($value)),
            'twitter' => array_filter([
                'twitter:card' => $image ? 'summary_large_image' : 'summary',
                'twitter:site' => $twitterSite,
                'twitter:title' => $ogTitle,
                'twitter:description' => $ogDescription,
                'twitter:image' => $image,
            ], fn ($value): bool => filled($value)),
            'json_ld' => array_values(array_filter($input['json_ld'] ?? [])),
        ];
    }

    public static function absoluteUrl(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return Str::startsWith($value, ['http://', 'https://']) ? $value : url($value);
    }

    /**
     * @param  list<array{question: string, answer: string}>  $faq
     * @return array<string, mixed>|null
     */
    public static function faqSchema(array $faq): ?array
    {
        $items = array_values(array_filter($faq, fn ($item): bool => is_array($item) && filled($item['question'] ?? null) && filled($item['answer'] ?? null)));

        if ($items === []) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn (array $item): array => [
                '@type' => 'Question',
                'name' => strip_tags($item['question']),
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($item['answer'])],
            ], $items),
        ];
    }

    /**
     * @param  list<array{0: string, 1: string}>  $crumbs  [name, url] pairs
     * @return array<string, mixed>
     */
    public static function breadcrumbSchema(array $crumbs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $crumb, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb[0],
                'item' => $crumb[1],
            ], $crumbs, array_keys($crumbs)),
        ];
    }

    /** @return array<string, mixed> */
    public static function organizationSchema(): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => SeoSettings::get('site_name', config('app.name')),
            'url' => url('/'),
            'logo' => self::absoluteUrl(SeoSettings::get('organization_logo')),
            'sameAs' => array_values(array_filter((array) SeoSettings::get('same_as', []))),
        ], fn ($value): bool => filled($value));
    }

    /** @return array<string, mixed> */
    public static function websiteSchema(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => SeoSettings::get('site_name', config('app.name')),
            'url' => url('/'),
            'inLanguage' => 'fa-IR',
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => route('blog').'?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /**
     * Meta for a static page (home, about, contact, blog) from SEO settings.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function forPage(string $page, array $extra = []): array
    {
        $settings = (array) SeoSettings::get('pages.'.$page, []);
        $defaults = SeoSettings::defaults()['pages'][$page] ?? [];

        return [
            'title' => filled($settings['title'] ?? null) ? $settings['title'] : ($defaults['title'] ?? config('app.name')),
            'description' => filled($settings['description'] ?? null) ? $settings['description'] : ($defaults['description'] ?? ''),
            'og_title' => $settings['og_title'] ?? null,
            'og_description' => $settings['og_description'] ?? null,
            'og_image' => $settings['og_image'] ?? null,
            'noindex' => (bool) ($settings['noindex'] ?? false),
            ...$extra,
        ];
    }

    private static function twitterHandle(?string $handle): ?string
    {
        $handle = trim((string) $handle);

        return $handle === '' ? null : '@'.ltrim($handle, '@');
    }
}
