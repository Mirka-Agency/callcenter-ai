<?php

namespace App\Models;

use App\Domain\Blog\BlogPostStatus;
use Database\Factories\BlogPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'title',
    'slug',
    'body',
    'excerpt',
    'status',
    'category_id',
    'cover_image_url',
    'cover_video_url',
    'published_at',
    'author_user_id',
    'author_display_name',
    'owned_by_user_id',
    'is_recoverable_draft',
    'meta_title',
    'meta_description',
    'seo_keywords',
    'canonical_url',
    'og_title',
    'og_description',
    'og_image_url',
    'faq_json',
    'content_version',
])]
class BlogPost extends Model
{
    /** @use HasFactory<BlogPostFactory> */
    use HasFactory;

    public const PLACEHOLDER_TITLE = 'پیش‌نویس بدون عنوان';

    protected function casts(): array
    {
        return [
            'status' => BlogPostStatus::class,
            'published_at' => 'datetime',
            'is_recoverable_draft' => 'boolean',
            'faq_json' => 'array',
            'content_version' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owned_by_user_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', BlogPostStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', Carbon::now('UTC'));
    }

    public function isPublished(): bool
    {
        return $this->status === BlogPostStatus::Published
            && $this->published_at !== null
            && $this->published_at->lte(Carbon::now('UTC'));
    }

    public function isDraft(): bool
    {
        return $this->status === BlogPostStatus::Draft;
    }

    public function usesPlaceholderTitle(): bool
    {
        return trim((string) $this->title) === self::PLACEHOLDER_TITLE
            || trim((string) $this->title) === '';
    }
}
