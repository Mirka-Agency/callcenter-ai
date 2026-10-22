<?php

namespace App\Application\Blog\Support;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Support\Str;

final class BlogSlugger
{
    public function fromTitle(string $title, int $maxLength = 180): string
    {
        $slug = trim($title);
        $slug = Normalizer::normalize($slug);
        $slug = preg_replace('/[\s_\x{200C}]+/u', '-', $slug) ?? $slug;
        $slug = preg_replace('/[^\p{L}\p{N}\-]+/u', '', $slug) ?? $slug;
        $slug = preg_replace('/-+/', '-', $slug) ?? $slug;
        $slug = trim($slug, '-');
        $slug = mb_strtolower($slug);

        if ($slug === '') {
            $slug = 'post-'.Str::lower(Str::random(8));
        }

        return mb_substr($slug, 0, $maxLength);
    }

    public function uniquePostSlug(string $base, ?int $ignoreId = null): string
    {
        $candidate = $base !== '' ? $base : 'post-'.Str::lower(Str::random(8));
        $suffix = 2;

        while ($this->postSlugExists($candidate, $ignoreId)) {
            $candidate = mb_substr($base, 0, 160).'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    public function uniqueCategorySlug(string $base, ?int $ignoreId = null): string
    {
        $candidate = $base !== '' ? $base : 'category-'.Str::lower(Str::random(8));
        $suffix = 2;

        while ($this->categorySlugExists($candidate, $ignoreId)) {
            $candidate = mb_substr($base, 0, 160).'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function postSlugExists(string $slug, ?int $ignoreId): bool
    {
        return BlogPost::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }

    private function categorySlugExists(string $slug, ?int $ignoreId): bool
    {
        return BlogCategory::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }
}
