<?php

namespace App\Filament\Concerns;

use App\Enums\UserRole;

trait OnlySuperAdmin
{
    public static function canAccess(): bool
    {
        return static::isAvailableInCurrentDeployment()
            && auth()->user()?->role === UserRole::SuperAdmin;
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    protected static function isAvailableInCurrentDeployment(): bool
    {
        return true;
    }
}
