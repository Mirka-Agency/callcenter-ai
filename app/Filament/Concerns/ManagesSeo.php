<?php

namespace App\Filament\Concerns;

use App\Domain\Platform\Enums\AdminArea;

/**
 * Site-wide SEO tools (redirects, robots.txt, settings): super admins and admins with the
 * Content area. Writers only edit the SEO fields of their posts and categories.
 */
trait ManagesSeo
{
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->role?->isAdminStaff() === true && $user->hasAdminArea(AdminArea::Content);
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}
