<?php

namespace App\Application\Blog;

use App\Application\Blog\Support\Normalizer;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Support\JalaliDate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PublicBlogQuery
{
    public const PER_PAGE = 6;

    /**
     * @return list<array{slug: ?string, label: string, icon: string, count: int}>
     */
    public function filters(): array
    {
        $publishedCounts = BlogPost::query()
            ->published()
            ->selectRaw('category_id, count(*) as aggregate')
            ->groupBy('category_id')
            ->pluck('aggregate', 'category_id');

        $total = (int) $publishedCounts->sum();

        $filters = [[
            'slug' => null,
            'label' => 'همه مطالب',
            'icon' => 'grid',
            'count' => $total,
        ]];

        $categories = BlogCategory::query()->orderBy('name')->get();

        foreach ($categories as $category) {
            $filters[] = [
                'slug' => $category->slug,
                'label' => $category->name,
                'icon' => $category->icon ?: 'doc',
                'count' => (int) ($publishedCounts[$category->id] ?? 0),
            ];
        }

        return $filters;
    }

    public function resolveCategorySlug(?string $slug): ?string
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        return BlogCategory::query()->where('slug', $slug)->exists() ? $slug : null;
    }

    public function paginate(?string $categorySlug, ?string $query, int $page): LengthAwarePaginator
    {
        $builder = $this->publishedQuery()
            ->with('category')
            ->when($categorySlug, function (Builder $q) use ($categorySlug): void {
                $q->whereHas('category', fn (Builder $category) => $category->where('slug', $categorySlug));
            })
            ->when($query, function (Builder $q) use ($query): void {
                $needle = Normalizer::normalize($query);
                $q->where(function (Builder $inner) use ($needle): void {
                    $inner->where('title', 'like', '%'.$needle.'%')
                        ->orWhere('excerpt', 'like', '%'.$needle.'%');
                });
            })
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        $paginator = $builder->paginate(self::PER_PAGE, ['*'], 'page', max(1, $page));

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (BlogPost $post): array => $this->present($post))
        );

        $paginator->withPath(route('blog'))->appends(array_filter([
            'category' => $categorySlug,
            'q' => $query,
        ]));

        return $paginator;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPublishedBySlug(string $slug): ?array
    {
        $post = $this->publishedQuery()
            ->with('category')
            ->where('slug', $slug)
            ->first();

        return $post instanceof BlogPost ? $this->present($post, withBody: true) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function related(string $slug, int $limit = 3): array
    {
        $current = $this->publishedQuery()->with('category')->where('slug', $slug)->first();
        if (! $current instanceof BlogPost) {
            return [];
        }

        $sameCategory = $this->publishedQuery()
            ->with('category')
            ->where('id', '!=', $current->id)
            ->when($current->category_id, fn (Builder $q) => $q->where('category_id', $current->category_id))
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();

        $remaining = $limit - $sameCategory->count();
        $others = collect();
        if ($remaining > 0) {
            $excludeIds = $sameCategory->pluck('id')->push($current->id)->all();
            $others = $this->publishedQuery()
                ->with('category')
                ->whereNotIn('id', $excludeIds)
                ->orderByDesc('published_at')
                ->limit($remaining)
                ->get();
        }

        return $sameCategory
            ->concat($others)
            ->map(fn (BlogPost $post): array => $this->present($post))
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function publishedSlugs(): array
    {
        return $this->publishedQuery()
            ->orderByDesc('published_at')
            ->pluck('slug')
            ->all();
    }

    private function publishedQuery(): Builder
    {
        return BlogPost::query()->published();
    }

    /**
     * @return array<string, mixed>
     */
    public function present(BlogPost $post, bool $withBody = false): array
    {
        $categorySlug = $post->category?->slug ?? 'guide';
        $minutes = $this->estimateReadingMinutes($post->body);

        $payload = [
            'id' => $post->id,
            'slug' => $post->slug,
            'category' => $categorySlug,
            'category_label' => $post->category?->name ?? '',
            'category_icon' => $post->category?->icon ?: 'doc',
            'title' => $post->title,
            'excerpt' => $post->excerpt ?? '',
            'minutes' => $minutes,
            'published_on' => $post->published_at
                ? JalaliDate::format($post->published_at)
                : '',
            'published_on_sort' => $post->published_at?->toDateString(),
            'tone' => (abs(crc32((string) $post->slug)) % 3) + 1,
            'cover_image_url' => $post->cover_image_url,
            'cover_video_url' => $post->cover_video_url,
            'author_display_name' => $post->author_display_name,
            'meta_title' => $post->meta_title ?: $post->title,
            'meta_description' => $post->meta_description ?: ($post->excerpt ?? ''),
            'seo_keywords' => $post->seo_keywords,
            'canonical_url' => $post->canonical_url ?: route('blog.show', $post->slug),
            'og_title' => $post->og_title ?: ($post->meta_title ?: $post->title),
            'og_description' => $post->og_description ?: ($post->meta_description ?: ($post->excerpt ?? '')),
            'og_image_url' => $post->og_image_url ?: $post->cover_image_url,
            'faq' => is_array($post->faq_json) ? $post->faq_json : [],
            'body_html' => $post->body,
        ];

        if ($withBody) {
            $payload['body'] = $this->plainParagraphs($post->body);
        }

        return $payload;
    }

    /**
     * @return list<string>
     */
    private function plainParagraphs(string $html): array
    {
        $text = html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], "\n", $html)));
        $parts = preg_split('/\n+/u', $text) ?: [];

        return array_values(array_filter(array_map(
            fn (string $line): string => trim($line),
            $parts,
        ), fn (string $line): bool => $line !== ''));
    }

    private function estimateReadingMinutes(string $html): int
    {
        $text = trim(html_entity_decode(strip_tags($html)));
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $minutes = (int) max(1, (int) ceil(count($words) / 180));

        return $minutes;
    }
}
