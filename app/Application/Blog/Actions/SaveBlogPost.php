<?php

namespace App\Application\Blog\Actions;

use App\Application\Blog\BlogAuthorization;
use App\Application\Blog\Support\BlogPostEditLock;
use App\Application\Blog\Support\BlogSlugger;
use App\Application\Blog\Support\FaqNormalizer;
use App\Application\Blog\Support\HtmlSanitizer;
use App\Application\Blog\Support\SafeUrlValidator;
use App\Domain\Blog\BlogPostStatus;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveBlogPost
{
    public function __construct(
        private readonly BlogAuthorization $auth,
        private readonly BlogSlugger $slugger,
        private readonly HtmlSanitizer $sanitizer,
        private readonly SafeUrlValidator $urls,
        private readonly FaqNormalizer $faq,
        private readonly BlogPostEditLock $lock,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, BlogPost $post, array $input, bool $requireLock = true): BlogPost
    {
        $this->auth->assertCanManage($actor);

        if ($requireLock && ! $this->lock->isHeldBy((int) $post->id, $actor->id)) {
            throw ValidationException::withMessages([
                'lock' => __('filament.blog.errors.lock_required'),
            ]);
        }

        $expectedVersion = isset($input['content_version']) ? (int) $input['content_version'] : null;
        if ($expectedVersion !== null && $expectedVersion !== (int) $post->content_version) {
            throw ValidationException::withMessages([
                'content_version' => __('filament.blog.errors.version_conflict'),
            ]);
        }

        $publish = (bool) ($input['publish'] ?? ($post->status === BlogPostStatus::Published));
        $title = trim((string) ($input['title'] ?? ''));
        $slugInput = trim((string) ($input['slug'] ?? ''));
        $body = $this->sanitizer->sanitize((string) ($input['body'] ?? ''));

        if (mb_strlen($body) > 500_000) {
            throw ValidationException::withMessages([
                'body' => __('filament.blog.validation.body_too_long'),
            ]);
        }

        if ($publish) {
            if ($title === '' || $title === BlogPost::PLACEHOLDER_TITLE) {
                throw ValidationException::withMessages([
                    'title' => __('filament.blog.validation.publish_title_required'),
                ]);
            }
        } else {
            if ($title === '') {
                $title = BlogPost::PLACEHOLDER_TITLE;
            }
        }

        if (mb_strlen($title) > 300) {
            throw ValidationException::withMessages([
                'title' => __('filament.blog.validation.title_too_long'),
            ]);
        }

        $baseSlug = $slugInput !== ''
            ? $this->slugger->fromTitle($slugInput)
            : $this->slugger->fromTitle($title === BlogPost::PLACEHOLDER_TITLE ? 'draft-'.$post->id : $title);

        $categoryId = $input['category_id'] ?? null;
        if ($categoryId !== null && $categoryId !== '') {
            $categoryId = (int) $categoryId;
            if (! BlogCategory::query()->whereKey($categoryId)->exists()) {
                throw ValidationException::withMessages([
                    'category_id' => __('filament.blog.validation.category_missing'),
                ]);
            }
        } else {
            $categoryId = null;
        }

        $authorUserId = $input['author_user_id'] ?? null;
        $authorDisplayName = trim((string) ($input['author_display_name'] ?? ''));
        if ($authorUserId !== null && $authorUserId !== '') {
            $authorUserId = (int) $authorUserId;
            $author = User::query()->find($authorUserId);
            if (! $author instanceof User || ! $this->auth->canSelectAsAuthor($author)) {
                throw ValidationException::withMessages([
                    'author_user_id' => __('filament.blog.validation.author_invalid'),
                ]);
            }
            if ($authorDisplayName === '') {
                $authorDisplayName = $author->name;
            }
        } else {
            $authorUserId = null;
            $authorDisplayName = $authorDisplayName !== '' ? $authorDisplayName : null;
        }

        $publishedAt = $this->resolvePublishedAt($publish, $input['published_at'] ?? null, $post);

        return DB::transaction(function () use (
            $post,
            $title,
            $baseSlug,
            $body,
            $input,
            $publish,
            $categoryId,
            $authorUserId,
            $authorDisplayName,
            $publishedAt,
        ): BlogPost {
            $post->refresh();

            $post->fill([
                'title' => $title,
                'slug' => $this->slugger->uniquePostSlug($baseSlug, (int) $post->id),
                'body' => $body,
                'excerpt' => $this->nullableString($input['excerpt'] ?? null, 5000),
                'status' => $publish ? BlogPostStatus::Published : BlogPostStatus::Draft,
                'category_id' => $categoryId,
                'cover_image_url' => $this->urls->optional(
                    isset($input['cover_image_url']) ? (string) $input['cover_image_url'] : null,
                    'cover_image_url',
                ),
                'cover_video_url' => $this->urls->optional(
                    isset($input['cover_video_url']) ? (string) $input['cover_video_url'] : null,
                    'cover_video_url',
                ),
                'published_at' => $publishedAt,
                'author_user_id' => $authorUserId,
                'author_display_name' => $authorDisplayName,
                'is_recoverable_draft' => false,
                'meta_title' => $this->nullableString($input['meta_title'] ?? null, 300),
                'meta_description' => $this->nullableString($input['meta_description'] ?? null, 5000),
                'seo_keywords' => $this->nullableString($input['seo_keywords'] ?? null, 1000),
                'canonical_url' => $this->urls->optional(
                    isset($input['canonical_url']) ? (string) $input['canonical_url'] : null,
                    'canonical_url',
                ),
                'og_title' => $this->nullableString($input['og_title'] ?? null, 300),
                'og_description' => $this->nullableString($input['og_description'] ?? null, 5000),
                'og_image_url' => $this->urls->optional(
                    isset($input['og_image_url']) ? (string) $input['og_image_url'] : null,
                    'og_image_url',
                ),
                'faq_json' => $this->faq->normalize($input['faq_json'] ?? []),
                'noindex' => (bool) ($input['noindex'] ?? $post->noindex),
                'content_version' => (int) $post->content_version + 1,
            ]);

            $post->save();

            return $post->fresh(['category', 'author']) ?? $post;
        });
    }

    private function resolvePublishedAt(bool $publish, mixed $raw, BlogPost $post): ?Carbon
    {
        if (! $publish) {
            return $post->published_at;
        }

        if ($raw === null || $raw === '') {
            return $post->published_at ?? Carbon::now('UTC');
        }

        try {
            $date = $raw instanceof Carbon ? $raw->clone() : Carbon::parse($raw);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'published_at' => __('filament.blog.validation.published_at_invalid'),
            ]);
        }

        $date = $date->utc();

        if ($date->gt(Carbon::now('UTC')->addMinute())) {
            throw ValidationException::withMessages([
                'published_at' => __('filament.blog.validation.published_at_future'),
            ]);
        }

        return $date;
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $max) {
            throw ValidationException::withMessages([
                'field' => __('filament.blog.validation.field_too_long'),
            ]);
        }

        return $value;
    }
}
