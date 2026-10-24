<?php

namespace App\Http\Controllers\Seo;

use App\Application\Blog\Support\BlogMediaUrl;
use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Support\LandingHost;
use App\Support\Seo\SeoMeta;
use App\Support\Seo\SeoSettings;
use App\Support\Seo\SitemapCache;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * /sitemap.xml is a sitemap index pointing to one child sitemap per content type.
 */
class SitemapController extends Controller
{
    public const CHILDREN = ['pages', 'categories', 'posts'];

    public function index(Request $request): Response
    {
        abort_unless(LandingHost::matches($request), 404);

        return $this->xml(SitemapCache::remember('index', fn (): string => view('seo.sitemap-index', [
            'sitemaps' => collect(self::CHILDREN)->map(fn (string $child): array => [
                'loc' => route('sitemap.child', $child),
                'lastmod' => $this->lastModified($child),
            ])->all(),
        ])->render()));
    }

    public function child(Request $request, string $child): Response
    {
        abort_unless(LandingHost::matches($request) && in_array($child, self::CHILDREN, true), 404);

        return $this->xml(SitemapCache::remember($child, fn (): string => view('seo.sitemap', [
            'urls' => match ($child) {
                'pages' => $this->pages(),
                'categories' => $this->categories(),
                'posts' => $this->posts(),
            },
        ])->render()));
    }

    /** @return list<array{loc: string, lastmod: string|null}> */
    private function pages(): array
    {
        $latestPost = $this->lastModified('posts');

        return collect(SeoSettings::PAGES)
            ->reject(fn (string $page): bool => (bool) SeoSettings::get('pages.'.$page.'.noindex', false))
            ->map(fn (string $page): array => ['loc' => route($page), 'lastmod' => $page === 'blog' ? $latestPost : null])
            ->values()
            ->all();
    }

    /** @return list<array{loc: string, lastmod: string|null}> */
    private function categories(): array
    {
        $latestByCategory = BlogPost::query()->published()
            ->selectRaw('category_id, MAX(updated_at) as aggregate')
            ->groupBy('category_id')
            ->pluck('aggregate', 'category_id');

        return BlogCategory::query()
            ->where('noindex', false)
            ->whereNull('canonical_url')
            ->whereHas('posts', fn ($query) => $query->published())
            ->orderBy('name')
            ->get(['id', 'slug', 'updated_at'])
            ->map(fn (BlogCategory $category): array => [
                'loc' => route('blog.category', $category->slug),
                'lastmod' => collect([$category->updated_at, isset($latestByCategory[$category->id]) ? Carbon::parse($latestByCategory[$category->id]) : null])
                    ->filter()->max()?->toAtomString(),
            ])
            ->all();
    }

    /** @return list<array{loc: string, lastmod: string|null, images: list<string>}> */
    private function posts(): array
    {
        return BlogPost::query()->published()
            ->where('noindex', false)
            ->whereNull('canonical_url')
            ->orderByDesc('published_at')
            ->get(['id', 'slug', 'updated_at', 'cover_image_url', 'og_image_url'])
            ->map(fn (BlogPost $post): array => [
                'loc' => route('blog.show', $post->slug),
                'lastmod' => $post->updated_at?->toAtomString(),
                'images' => collect([$post->cover_image_url, $post->og_image_url])
                    ->filter()
                    ->map(fn (string $url): ?string => SeoMeta::absoluteUrl(BlogMediaUrl::toPublicUrl($url)))
                    ->filter()->unique()->values()->all(),
            ])
            ->all();
    }

    private function lastModified(string $child): ?string
    {
        $value = match ($child) {
            'posts' => BlogPost::query()->published()->max('updated_at'),
            'categories' => BlogCategory::query()->max('updated_at'),
            default => null,
        };

        return $value ? Carbon::parse($value)->toAtomString() : null;
    }

    private function xml(string $body): Response
    {
        return response($body, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
