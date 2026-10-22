<?php

namespace App\Application\Blog\Actions;

use App\Application\Blog\BlogAuthorization;
use App\Application\Blog\Support\BlogSlugger;
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
            ]);
            $category->save();

            return $category->fresh() ?? $category;
        });
    }
}
