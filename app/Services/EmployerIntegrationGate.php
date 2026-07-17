<?php

namespace App\Services;

use App\Models\Organization;

class EmployerIntegrationGate
{
    public static function allowsFullManagement(?Organization $organization = null): bool
    {
        $organization ??= EmployerContext::organization();

        return (bool) $organization->employer_can_manage_integrations;
    }

    public static function authorizeFullManagement(?Organization $organization = null): void
    {
        if (! self::allowsFullManagement($organization)) {
            abort(403, 'مدیریت کامل یکپارچه‌سازی برای این سازمان فعال نیست.');
        }
    }
}
