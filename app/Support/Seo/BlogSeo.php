<?php

namespace App\Support\Seo;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Builds the SEO input (see SeoMeta::resolve) for the blog index, category pages and posts.
 */
class BlogSeo
{
    /** @return array<string, mixed> */
    public static function index(LengthAwarePaginator $posts, string $query): array
    {
        return SeoMeta::forPage('blog', [
            'canonical' => self::pagedUrl(route('blog'), $posts),
            'noindex' => $query !== '',
            'json_ld' => [
                SeoMeta::breadcrumbSchema([['خانه', route('home')], ['وبلاگ', route('blog')]]),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $category
     * @return array<string, mixed>
     */
    public static function category(array $category, LengthAwarePaginator $posts, string $query): array
    {
        $url = route('blog.category', $category['slug']);

        return [
            'title' => $category['meta_title'].self::suffix(),
            'description' => $category['meta_description'],
            'canonical' => $posts->currentPage() > 1 ? self::pagedUrl($url, $posts) : $category['canonical_url'],
            'og_title' => $category['og_title'] ?: $category['meta_title'],
            'og_description' => $category['og_description'],
            'og_image' => $category['og_image_url'],
            'noindex' => $category['noindex'] || $query !== '',
            'json_ld' => [
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'CollectionPage',
                    'name' => $category['meta_title'],
                    'description' => $category['meta_description'],
                    'url' => $url,
                    'inLanguage' => 'fa-IR',
                ],
                SeoMeta::breadcrumbSchema([['خانه', route('home')], ['وبلاگ', route('blog')], [$category['name'], $url]]),
                SeoMeta::faqSchema($category['faq']),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $post
     * @return array<string, mixed>
     */
    public static function post(array $post): array
    {
        $url = route('blog.show', $post['slug']);
        $image = SeoMeta::absoluteUrl($post['og_image_url'] ?? null) ?? SeoMeta::absoluteUrl(SeoSettings::get('default_og_image'));
        $crumbs = [['خانه', route('home')], ['وبلاگ', route('blog')]];

        if (filled($post['category_label'] ?? null)) {
            $crumbs[] = [$post['category_label'], route('blog.category', $post['category'])];
        }

        $crumbs[] = [$post['title'], $url];

        return [
            'title' => $post['meta_title'].self::suffix(),
            'description' => $post['meta_description'],
            'canonical' => $post['canonical_url'],
            'og_type' => 'article',
            'og_title' => $post['og_title'],
            'og_description' => $post['og_description'],
            'og_image' => $post['og_image_url'],
            'noindex' => $post['noindex'] ?? false,
            'published_time' => $post['published_at_iso'] ?? null,
            'modified_time' => $post['updated_at_iso'] ?? null,
            'section' => $post['category_label'] ?: null,
            'author' => $post['author_display_name'] ?? null,
            'json_ld' => [
                array_filter([
                    '@context' => 'https://schema.org',
                    '@type' => 'BlogPosting',
                    'headline' => mb_substr($post['title'], 0, 110),
                    'description' => $post['meta_description'],
                    'image' => $image ? [$image] : null,
                    'datePublished' => $post['published_at_iso'] ?? null,
                    'dateModified' => $post['updated_at_iso'] ?? null,
                    'inLanguage' => 'fa-IR',
                    'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
                    'articleSection' => $post['category_label'] ?: null,
                    'keywords' => $post['seo_keywords'] ?? null,
                    'author' => filled($post['author_display_name'] ?? null)
                        ? ['@type' => 'Person', 'name' => $post['author_display_name']]
                        : ['@type' => 'Organization', 'name' => SeoSettings::get('site_name', config('app.name'))],
                    'publisher' => SeoMeta::organizationSchema(),
                ], fn ($value): bool => filled($value)),
                SeoMeta::breadcrumbSchema($crumbs),
                SeoMeta::faqSchema($post['faq'] ?? []),
            ],
        ];
    }

    public static function suffix(): string
    {
        return (string) (SeoSettings::all()['blog_title_suffix'] ?? '');
    }

    private static function pagedUrl(string $url, LengthAwarePaginator $posts): string
    {
        return $posts->currentPage() > 1 ? $url.'?page='.$posts->currentPage() : $url;
    }
}
