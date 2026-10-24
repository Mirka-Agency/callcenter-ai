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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AutosaveBlogPost
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
    public function handle(User $actor, BlogPost $post, array $input): BlogPost
    {
        $this->auth->assertCanManage($actor);

        if ($post->status !== BlogPostStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => __('filament.blog.errors.autosave_published'),
            ]);
        }

        if (! $this->lock->isHeldBy((int) $post->id, $actor->id)) {
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

        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            $title = BlogPost::PLACEHOLDER_TITLE;
        }

        if (mb_strlen($title) > 300) {
            throw ValidationException::withMessages([
                'title' => __('filament.blog.validation.title_too_long'),
            ]);
        }

        $body = $this->sanitizer->sanitize((string) ($input['body'] ?? ''));
        if (mb_strlen($body) > 500_000) {
            throw ValidationException::withMessages([
                'body' => __('filament.blog.validation.body_too_long'),
            ]);
        }

        $slugInput = trim((string) ($input['slug'] ?? ''));
        $baseSlug = $slugInput !== ''
            ? $this->slugger->fromTitle($slugInput)
            : $this->slugger->fromTitle($title === BlogPost::PLACEHOLDER_TITLE ? 'draft-'.$post->id : $title);

        $categoryId = $input['category_id'] ?? null;
        if ($categoryId !== null && $categoryId !== '') {
            $categoryId = (int) $categoryId;
            if (! BlogCategory::query()->whereKey($categoryId)->exists()) {
                $categoryId = $post->category_id;
            }
        } else {
            $categoryId = null;
        }

        return DB::transaction(function () use ($post, $title, $baseSlug, $body, $input, $categoryId): BlogPost {
            $post->refresh();

            // Never change publish status via autosave.
            $post->fill([
                'title' => $title,
                'slug' => $this->slugger->uniquePostSlug($baseSlug, (int) $post->id),
                'body' => $body,
                'excerpt' => $this->nullableString($input['excerpt'] ?? null, 5000),
                'category_id' => $categoryId,
                'cover_image_url' => $this->urls->optional(
                    array_key_exists('cover_image_url', $input) ? (string) ($input['cover_image_url'] ?? '') : $post->cover_image_url,
                    'cover_image_url',
                ),
                'cover_video_url' => $this->urls->optional(
                    array_key_exists('cover_video_url', $input) ? (string) ($input['cover_video_url'] ?? '') : $post->cover_video_url,
                    'cover_video_url',
                ),
                'meta_title' => $this->nullableString($input['meta_title'] ?? $post->meta_title, 300),
                'meta_description' => $this->nullableString($input['meta_description'] ?? $post->meta_description, 5000),
                'seo_keywords' => $this->nullableString($input['seo_keywords'] ?? $post->seo_keywords, 1000),
                'canonical_url' => $this->urls->optional(
                    array_key_exists('canonical_url', $input) ? (string) ($input['canonical_url'] ?? '') : $post->canonical_url,
                    'canonical_url',
                ),
                'og_title' => $this->nullableString($input['og_title'] ?? $post->og_title, 300),
                'og_description' => $this->nullableString($input['og_description'] ?? $post->og_description, 5000),
                'og_image_url' => $this->urls->optional(
                    array_key_exists('og_image_url', $input) ? (string) ($input['og_image_url'] ?? '') : $post->og_image_url,
                    'og_image_url',
                ),
                'faq_json' => array_key_exists('faq_json', $input)
                    ? $this->faq->normalize($input['faq_json'])
                    : ($post->faq_json ?? []),
                'noindex' => array_key_exists('noindex', $input) ? (bool) $input['noindex'] : $post->noindex,
                'content_version' => (int) $post->content_version + 1,
            ]);

            $post->save();

            return $post->fresh(['category', 'author']) ?? $post;
        });
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
            return mb_substr($value, 0, $max);
        }

        return $value;
    }
}
