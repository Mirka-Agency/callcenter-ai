<?php

namespace App\Filament\Concerns;

/**
 * Restricts a page or resource to super admins and admins, hiding it from writers.
 */
trait AdminStaffOnly
{
    public static function canAccess(): bool
    {
        return auth()->user()?->role?->isAdminStaff() === true;
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
