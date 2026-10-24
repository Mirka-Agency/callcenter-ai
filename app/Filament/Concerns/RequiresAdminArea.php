<?php

namespace App\Filament\Concerns;

use App\Domain\Platform\Enums\AdminArea;

/**
 * Restricts a page or resource to super admins and admins granted the area returned by adminArea().
 */
trait RequiresAdminArea
{
    abstract public static function adminArea(): AdminArea;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAdminArea(static::adminArea()) === true;
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
