<?php

namespace App\Models;

use App\Support\Seo\SitemapCache;
use App\Support\Seo\SlugRedirects;
use Database\Factories\BlogCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'description',
    'image_url',
    'icon',
    'meta_title',
    'meta_description',
    'canonical_url',
    'og_title',
    'og_description',
    'og_image_url',
    'faq_json',
    'noindex',
])]
class BlogCategory extends Model
{
    /** @use HasFactory<BlogCategoryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'faq_json' => 'array',
            'noindex' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (BlogCategory $category) => SlugRedirects::handleSaved($category, 'blog.category'));
        static::deleted(fn () => SitemapCache::flush());
    }

    public function publicUrl(): string
    {
        return route('blog.category', $this->slug);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(BlogPost::class, 'category_id');
    }
}
