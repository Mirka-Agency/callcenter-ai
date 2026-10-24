<?php

namespace App\Support\Seo;

use App\Domain\Seo\RedirectStatus;
use App\Models\SeoRedirect;
use Illuminate\Database\Eloquent\Model;

/**
 * Keeps public URLs working when a post or category slug changes: adds a 301 from the old URL,
 * points older redirects at the new URL, and removes any rule that would hide the new URL.
 */
class SlugRedirects
{
    public static function handleSaved(Model $model, string $routeName, bool $oldUrlWasPublic = true): void
    {
        SitemapCache::flush();

        $newPath = RedirectResolver::normalizePath(route($routeName, $model->getAttribute('slug'), false));

        SeoRedirect::query()->where('match_type', SeoRedirect::EXACT)->where('source_path', $newPath)->delete();

        if (! $model->wasChanged('slug') || ! $oldUrlWasPublic || blank($model->getOriginal('slug'))) {
            return;
        }

        $oldPath = RedirectResolver::normalizePath(route($routeName, $model->getOriginal('slug'), false));

        if ($oldPath === $newPath) {
            return;
        }

        $redirect = SeoRedirect::query()->updateOrCreate(
            ['source_path' => $oldPath, 'match_type' => SeoRedirect::EXACT],
            [
                'target_url' => $newPath,
                'status_code' => RedirectStatus::MovedPermanently,
                'is_active' => true,
                'notes' => __('seo.redirects.auto_note'),
                'created_by_user_id' => auth()->id(),
            ],
        );

        app(RedirectResolver::class)->flattenChainsTo($oldPath, $newPath, $redirect->id);
    }
}
