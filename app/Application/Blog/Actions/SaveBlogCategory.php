<?php

namespace App\Application\Blog\Actions;

use App\Application\Blog\BlogAuthorization;
use App\Application\Blog\Support\BlogSlugger;
use App\Application\Blog\Support\FaqNormalizer;
use App\Application\Blog\Support\HtmlSanitizer;
use App\Application\Blog\Support\SafeUrlValidator;
use App\Models\BlogCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveBlogCategory
{
    public function __construct(
        private readonly BlogAuthorization $auth,
        private readonly BlogSlugger $slugger,
        private readonly HtmlSanitizer $sanitizer,
        private readonly SafeUrlValidator $urls,
        private readonly FaqNormalizer $faq,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, ?BlogCategory $category, array $input): BlogCategory
    {
        $this->auth->assertCanManage($actor);

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 200) {
            throw ValidationException::withMessages([
                'name' => __('filament.blog.validation.category_name'),
            ]);
        }

        $slugInput = trim((string) ($input['slug'] ?? ''));
        $baseSlug = $this->slugger->fromTitle($slugInput !== '' ? $slugInput : $name);

        $description = $this->sanitizer->sanitize((string) ($input['description'] ?? ''));
        if (mb_strlen($description) > 100_000) {
            throw ValidationException::withMessages([
                'description' => __('filament.blog.validation.description_too_long'),
            ]);
        }

        $icon = trim((string) ($input['icon'] ?? ''));
        if ($icon !== '' && mb_strlen($icon) > 40) {
            throw ValidationException::withMessages([
                'icon' => __('filament.blog.validation.icon_invalid'),
            ]);
        }

        return DB::transaction(function () use ($category, $name, $baseSlug, $description, $input, $icon): BlogCategory {
            $category ??= new BlogCategory;
            $ignoreId = $category->exists ? (int) $category->id : null;

            $category->fill([
                'name' => $name,
                'slug' => $this->slugger->uniqueCategorySlug($baseSlug, $ignoreId),
                'description' => $description,
                'image_url' => $this->urls->optional(
                    isset($input['image_url']) ? (string) $input['image_url'] : null,
                    'image_url',
                ),
                'icon' => $icon !== '' ? $icon : null,
                'meta_title' => $this->nullableString($input['meta_title'] ?? null, 300),
                'meta_description' => $this->nullableString($input['meta_description'] ?? null, 5000),
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
                'noindex' => (bool) ($input['noindex'] ?? false),
            ]);
            $category->save();

            return $category->fresh() ?? $category;
        });
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
