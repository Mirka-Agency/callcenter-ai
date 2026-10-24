<?php

namespace App\Support;

use App\Enums\UserRole;

class AiInfrastructure
{
    public static function isVisible(): bool
    {
        $role = auth()->user()?->role;

        return $role instanceof UserRole && $role->isAdminStaff();
    }

    public static function activeLabel(): string
    {
        return 'فعال';
    }
}
